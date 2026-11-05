<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Actions\Messages\SaveMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MessageConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        // migrate:fresh/rollbackよりも先に、対象DBを設定値と実接続の両方で確認します。
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('testing', config('database.connections.mysql.database'));
        $this->assertSame('testing', DB::selectOne('SELECT DATABASE() AS name')->name);
    }

    private function fixture(bool $private): array
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $channel = app(SaveChannel::class)->create($owner, ['name' => '実ロック確認', 'is_private' => $private ? '1' : '0']);
        if ($private) {
            $channel->members()->attach($author);
        }
        $parent = app(SaveMessage::class)->create($owner, $channel, '親本文');
        $target = app(SaveMessage::class)->create($author, $channel, '返信原文', $parent);
        $this->assertSame(0, DB::transactionLevel());

        return ['owner' => $owner->id, 'author' => $author->id, 'channel' => $channel->id, 'parent' => $parent->id, 'target' => $target->id];
    }

    private function observer(): Connection
    {
        // compose.yamlのroot passwordはDB_PASSWORD。公開設定例から読まれた値だけを利用します。
        $observer = app('db.factory')->make(array_replace(config('database.connections.mysql'), ['username' => 'root', 'database' => 'testing']), 'concurrency_observer');
        $this->assertSame('testing', $observer->selectOne('SELECT DATABASE() AS name')->name);

        return $observer;
    }

    private function worker(array $input): array
    {
        $stream = new InputStream;
        $stream->write(json_encode($input, JSON_THROW_ON_ERROR)."\n");
        $process = new Process([PHP_BINARY, base_path('tests/Support/message-concurrency-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'testing',
            'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => config('database.connections.mysql.port'),
            'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'),
            'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ], $stream, 20);
        $process->start();

        return [$process, $stream];
    }

    private function event(Process $process, string $name): array
    {
        $deadline = microtime(true) + 10;
        do {
            foreach (explode("\n", trim($process->getOutput())) as $line) {
                $event = json_decode($line, true);
                if (($event['event'] ?? null) === 'error') {
                    $this->fail('Worker exception: '.$event['exception']);
                }
                if (($event['event'] ?? null) === $name) {
                    return $event;
                }
            }
            if (! $process->isRunning()) {
                $this->fail('Worker stopped before '.$name);
            }
            $process->checkTimeout();
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Timed out waiting for worker event '.$name);
    }

    private function waitForChannelLock(Connection $observer, Process $follower, int $leaderId, int $followerId): object
    {
        $deadline = microtime(true) + 10;
        do {
            // 観測専用root接続は、両者のconnection IDとtesting.channelsの待機だけを読み取ります。
            $wait = $observer->selectOne(
                'SELECT l.OBJECT_SCHEMA AS schema_name, l.OBJECT_NAME AS table_name, l.LOCK_STATUS AS lock_status '
                .'FROM performance_schema.data_lock_waits w '
                .'JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID '
                .'JOIN performance_schema.threads requesting ON requesting.THREAD_ID = w.REQUESTING_THREAD_ID '
                .'JOIN performance_schema.threads blocking ON blocking.THREAD_ID = w.BLOCKING_THREAD_ID '
                .'WHERE requesting.PROCESSLIST_ID = ? AND blocking.PROCESSLIST_ID = ? '
                .'AND l.OBJECT_SCHEMA = ? AND l.OBJECT_NAME = ?', [$followerId, $leaderId, 'testing', 'channels']);
            if ($wait) {
                return $wait;
            }
            if (! $follower->isRunning()) {
                $this->fail('Follower stopped before a database lock wait was observed');
            }
            $follower->checkTimeout();
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('No testing.channels lock wait observed');
    }

    private function compete(string $scenario, array $fixture): array
    {
        $leader = $follower = null;
        $leaderInput = $followerInput = null;
        $observer = $this->observer();
        try {
            [$leader, $leaderInput] = $this->worker($fixture + ['scenario' => $scenario, 'role' => 'leader']);
            $locked = $this->event($leader, 'locked');
            $this->assertSame(1, $locked['transaction_level']);
            [$follower, $followerInput] = $this->worker($fixture + ['scenario' => $scenario, 'role' => 'follower']);
            $started = $this->event($follower, 'started');
            $this->assertSame(0, $started['transaction_level']);
            $parentId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $observerId = (int) $observer->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertCount(4, array_unique([$parentId, $observerId, $locked['connection'], $started['connection']]));
            $wait = $this->waitForChannelLock($observer, $follower, $locked['connection'], $started['connection']);
            $this->assertSame('testing', $wait->schema_name);
            $this->assertSame('channels', $wait->table_name);
            $this->assertSame('WAITING', $wait->lock_status);
            $this->assertStringNotContainsString('"event":"result"', $follower->getOutput());
            $leaderInput->write("commit\n");
            $this->event($leader, 'committed');
            $this->assertSame('saved', $this->event($leader, 'result')['outcome']);
            $result = $this->event($follower, 'result');
            $leaderInput->close();
            $followerInput->close();
            $this->assertSame(0, $leader->wait());
            $this->assertSame(0, $follower->wait());

            return $result;
        } finally {
            // 途中失敗でもDB接続を持つ子を先に終了させ、ロックを解放してからmigrate:rollbackへ進みます。
            foreach ([$follower, $leader] as $process) {
                if ($process) {
                    $process->stop(0.2);
                    $this->assertFalse($process->isRunning());
                }
            }
            $leaderInput?->close();
            $followerInput?->close();
            $observer->disconnect();
        }
    }

    public function test_reply_waiting_for_membership_removal_is_denied_after_commit_without_saving(): void
    {
        $fixture = $this->fixture(true);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $result = $this->compete('membership', $fixture);
        $this->assertSame(['event' => 'result', 'outcome' => 'authorization', 'status' => 404], $result);
        $this->assertDatabaseMissing('channel_user', ['channel_id' => $fixture['channel'], 'user_id' => $fixture['author']]);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_reply_waiting_for_channel_deletion_is_denied_after_commit_without_saving(): void
    {
        $fixture = $this->fixture(false);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $result = $this->compete('channel', $fixture);
        $this->assertSame(['event' => 'result', 'outcome' => 'authorization', 'status' => 404], $result);
        $this->assertNotNull(DB::table('channels')->where('id', $fixture['channel'])->value('deleted_at'));
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_same_version_edits_wait_and_only_the_first_editor_saves(): void
    {
        $fixture = $this->fixture(false);
        $parent = Message::findOrFail($fixture['parent'])->toArray();
        $before = Message::findOrFail($fixture['target']);
        $result = $this->compete('version', $fixture);
        $this->assertSame(['event' => 'result', 'outcome' => 'conflict', 'version' => 2, 'body' => '先行編集の本文'], $result);
        $saved = $before->fresh();
        $this->assertSame('先行編集の本文', $saved->body);
        $this->assertSame(2, $saved->version);
        $this->assertNotNull($saved->edited_at);
        $this->assertSame(array_diff_key($before->toArray(), ['body' => true, 'version' => true, 'edited_at' => true]), array_diff_key($saved->toArray(), ['body' => true, 'version' => true, 'edited_at' => true]));
        $this->assertSame($parent, Message::findOrFail($fixture['parent'])->toArray());
        $this->assertDatabaseCount('messages', 2);
    }
}
