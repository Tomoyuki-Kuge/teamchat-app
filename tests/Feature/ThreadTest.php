<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Actions\Messages\SaveMessage;
use App\Exceptions\MessageConflict;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthUserSeeder;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MessageSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ThreadTest extends TestCase
{
    use RefreshDatabase;

    private function setupThread(bool $private = false): array
    {
        $user = User::factory()->create();
        $channel = app(SaveChannel::class)->create($user, ['name' => 'スレッド', 'is_private' => $private ? '1' : '0']);
        $parent = app(SaveMessage::class)->create($user, $channel, '親の本文');
        $this->actingAs($user);

        return [$user, $channel, $parent];
    }

    private function url(Channel $channel, Message $parent): string
    {
        return '/channels/'.$channel->id.'/threads/'.$parent->id;
    }

    private function messageUrl(Channel $channel, Message $message): string
    {
        return '/channels/'.$channel->id.'/messages/'.$message->id;
    }

    private function textarea($response, string $id): string
    {
        preg_match('/<textarea[^>]*id="'.$id.'"[^>]*>(.*?)<\/textarea>/s', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);

        return substr(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 1);
    }

    private function historyFixture(): array
    {
        $user = User::factory()->create();
        $channel = app(SaveChannel::class)->create($user, ['name' => '表示範囲', 'is_private' => '0']);
        $start = CarbonImmutable::parse('2026-10-28 00:00:00', 'UTC');
        $this->travelTo($start->addDays(2));
        $posts = collect(range(1, 21))->map(fn ($number) => Message::forceCreate([
            'channel_id' => $channel->id, 'user_id' => $user->id, 'parent_message_id' => null,
            'body' => '通常投稿'.$number, 'version' => 1, 'created_at' => $start->addMinutes($number),
        ]));
        $parent = $posts[14];
        $replies = collect(range(1, 21))->map(fn ($number) => Message::forceCreate([
            'channel_id' => $channel->id, 'user_id' => $user->id, 'parent_message_id' => $parent->id,
            'body' => '対象返信'.$number, 'version' => 1, 'created_at' => $start->addDay()->addMinutes($number),
        ]));
        Message::forceCreate([
            'channel_id' => $channel->id, 'user_id' => $user->id, 'parent_message_id' => $posts[13]->id,
            'body' => '別スレッドの返信', 'version' => 1, 'created_at' => $start->addDay()->addMinutes(21),
        ]);
        $this->actingAs($user);

        return [$user, $channel, $parent, $posts, $replies];
    }

    private function historyDom($response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    private function historyScope(string $side): string
    {
        $class = $side === 'messages' ? 'msg-list' : 'thread-panel__messages';

        return '//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]';
    }

    private function historyIds($response, string $side, Message $parent): array
    {
        $nodes = $this->historyDom($response)->query($this->historyScope($side).'//article');
        $ids = [];
        foreach ($nodes as $node) {
            // SC-06の左側に重複する親だけthread-left-IDを使うため、同じDB IDへ戻します。
            $this->assertMatchesRegularExpression('/^(message|thread-left)-[0-9]+$/D', $node->getAttribute('id'));
            $id = (int) preg_replace('/^(message|thread-left)-/', '', $node->getAttribute('id'));
            if ($side !== 'replies' || $id !== $parent->id) {
                $ids[] = $id;
            }
        }
        if ($side === 'replies') {
            $this->assertSame(1, $this->historyDom($response)->query($this->historyScope($side).'//article[@id="message-'.$parent->id.'"]')->length);
        }

        return $ids;
    }

    private function historyLink($response, string $side, array $expectedParameters, string $expectedAnchor): string
    {
        $links = $this->historyDom($response)->query($this->historyScope($side).'//div[contains(concat(" ", normalize-space(@class), " "), " message-history ")]/a');
        $this->assertSame(1, $links->length);
        $url = $links->item(0)->getAttribute('href');
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        $this->assertEquals($expectedParameters, $parameters);
        $this->assertSame($expectedAnchor, parse_url($url, PHP_URL_FRAGMENT));

        return $url;
    }

    public function test_ac_5_1_thread_opens_from_links_and_has_independent_parent_count_replies_and_close_link(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $this->get('/channels/'.$channel->id)->assertOk()->assertSee($this->url($channel, $parent), false);
        $page = $this->get($this->url($channel, $parent))->assertOk()->assertSee('返信0件')->assertSee('返信がありません')->assertSee('スレッドを閉じる');
        $this->assertSame('', $this->textarea($page, 'message-body'));
        $this->assertSame('', $this->textarea($page, 'reply-body'));
        preg_match_all('/\bid="([^"]+)"/', $page->getContent(), $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
        $edit = $this->get($this->url($channel, $parent).'?edit='.$parent->id)->assertOk();
        $this->assertSame(1, substr_count($edit->getContent(), 'id="edit-body"'));
        $this->assertSame('親の本文', $this->textarea($edit, 'edit-body'));
    }

    public function test_ac_5_2_reply_uses_path_parent_and_authenticated_author_and_only_resets_reply_range(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $this->post($this->url($channel, $parent).'/replies', ['body' => "\n返信 <本文> ", 'user_id' => 999, 'parent_message_id' => 999,
            'messages_until' => $parent->id, 'messages_pages' => 2])->assertStatus(303)
            ->assertRedirect($this->url($channel, $parent).'?messages_until='.$parent->id.'&messages_pages=2');
        $reply = $parent->replies()->sole();
        $this->assertSame($user->id, $reply->user_id);
        $this->assertSame("\n返信 <本文> ", $reply->body);
        $this->get('/channels/'.$channel->id)->assertOk()->assertSee('返信1件')->assertDontSee('返信 &lt;本文&gt;', false);
        $this->get($this->url($channel, $parent))->assertOk()->assertSee($reply->body)->assertSee('返信1件');
    }

    public function test_ac_5_3_parent_delete_preserves_replies_and_deleted_replies_keep_position_count_without_body(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $action = app(SaveMessage::class);
        $first = $action->create($user, $channel, '削除返信の秘密', $parent);
        $second = $action->create($user, $channel, '残す返信', $parent);
        $action->delete($user, $channel, $first);
        $before = $second->fresh()->toArray();
        $context = ['origin' => 'thread', 'thread' => $parent->id];
        $confirmation = $this->get($this->messageUrl($channel, $parent).'/delete-confirmation?'.http_build_query($context))->assertOk();
        $confirmation->assertSee('やめる')->assertSee('name="thread" value="'.$parent->id.'"', false);
        $this->assertNull($parent->fresh()->deleted_at);
        $this->delete($this->messageUrl($channel, $parent), $context)->assertStatus(303);
        $this->assertSame($before, $second->fresh()->toArray());
        $this->get($this->url($channel, $parent))->assertOk()->assertDontSee('親の本文')->assertDontSee('削除返信の秘密')
            ->assertSeeInOrder(['返信2件', 'このメッセージは削除されました', '残す返信']);
        $this->post($this->url($channel, $parent).'/replies', ['body' => '削除親への新規返信'])->assertStatus(303);
        $this->assertSame(3, $parent->replies()->count());
        $this->assertSame('削除返信の秘密', $first->fresh()->body);
    }

    #[DataProvider('invalidBodies')]
    public function test_ac_5_4_and_tp_4_11_invalid_input_keeps_both_ranges_and_restores_only_submitted_composer(mixed $body, string $error): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $reply = app(SaveMessage::class)->create($user, $channel, '既存返信', $parent);
        $context = ['origin' => 'thread', 'thread' => $parent->id, 'messages_until' => $parent->id, 'messages_pages' => 2,
            'replies_until' => $reply->id, 'replies_pages' => 3];
        foreach (['reply', 'message'] as $form) {
            $url = $form === 'reply' ? $this->url($channel, $parent).'/replies' : '/channels/'.$channel->id.'/messages';
            $snapshot = Message::orderBy('id')->get()->toArray();
            $response = $this->post($url, $context + ['body' => $body])->assertStatus(303)->assertSessionHasErrors('body');
            $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
            $location = $response->headers->get('Location');
            foreach (['messages_pages=2', 'replies_pages=3', 'messages_until='.$parent->id, 'replies_until='.$reply->id] as $parameter) {
                $this->assertStringContainsString($parameter, $location);
            }
            $page = $this->get($location)->assertOk()->assertSee($error);
            $this->assertSame(is_string($body) ? $body : '', $this->textarea($page, $form.'-body'));
            $this->assertSame('', $this->textarea($page, ($form === 'reply' ? 'message' : 'reply').'-body'));
            $this->assertSame(1, substr_count($page->getContent(), 'role="alert"'));
        }
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($this->messageUrl($channel, $reply), $context + ['body' => $body, 'expected_version' => $reply->version])
            ->assertStatus(303)->assertSessionHasErrors('body');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('messages', 2);
    }

    public static function invalidBodies(): array
    {
        return [[str_repeat('あ', 1001), 'メッセージは1,000文字以内で入力してください'], ['', 'メッセージを入力してください'], ["　 \n　", 'メッセージを入力してください'], [['配列'], 'メッセージを入力してください']];
    }

    #[DataProvider('replyCounts')]
    public function test_reply_history_is_independent_latest_ten_then_oldest_first_and_keeps_boundary_and_anchor(int $count): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $action = app(SaveMessage::class);
        $replies = collect();
        for ($i = 1; $i <= $count; $i++) {
            $replies->push($action->create($user, $channel, '返信番号'.$i.'末', $parent));
        }
        $page = $this->get($this->url($channel, $parent))->assertOk()->assertSee('返信'.$count.'件');
        if ($count <= 10) {
            $page->assertDontSee('以前の返信を表示');
        } else {
            $page->assertDontSee('返信番号1末')->assertSeeInOrder(['返信番号2末', '返信番号11末'])->assertSee('以前の返信を表示');
            preg_match('/href="([^"]+)"[^>]*>以前の返信を表示/', $page->getContent(), $link);
            $url = html_entity_decode($link[1]);
            $this->assertStringEndsWith('#message-'.$replies[1]->id, $url);
            $action->create($user, $channel, '新着は混ぜない', $parent);
            $this->get($url)->assertOk()->assertSeeInOrder(['返信番号1末', '返信番号11末'])->assertDontSee('新着は混ぜない')->assertSee('返信12件');
        }
    }

    public static function replyCounts(): array
    {
        return [[0], [10], [11]];
    }

    public function test_reply_edit_rechecks_versions_and_delete_confirmation_preserves_thread_ranges_and_draft_version(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $reply = app(SaveMessage::class)->create($user, $channel, '返信原文', $parent);
        $context = ['origin' => 'thread', 'thread' => $parent->id, 'messages_until' => $parent->id, 'messages_pages' => 2, 'replies_until' => $reply->id, 'replies_pages' => 2];
        $url = $this->messageUrl($channel, $reply);
        $this->get($this->url($channel, $parent).'?'.http_build_query($context + ['edit' => $reply->id]))->assertOk()->assertSee('返信原文');
        $this->patch($url, $context + ['body' => str_repeat('あ', 1000), 'expected_version' => 1])->assertStatus(303);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $invalid = $this->patch($url, $context + ['body' => '', 'expected_version' => 1])->assertStatus(303);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $page = $this->get($invalid->headers->get('Location'))->assertOk()->assertSee('name="expected_version" value="1"', false);
        $this->assertSame('', $this->textarea($page, 'edit-body'));
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($url, $context + ['body' => '自分の下書き', 'expected_version' => 1])->assertStatus(409)->assertSee('自分の下書き')->assertSee('name="expected_version" value="2"', false);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        app(SaveMessage::class)->update($user, $channel, $reply, 'さらに先行編集', 2);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($url, $context + ['body' => '自分の下書き', 'expected_version' => 2])->assertStatus(409)->assertSee('さらに先行編集')->assertSee('name="expected_version" value="3"', false);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $this->patch($url, $context + ['body' => '確認した内容', 'expected_version' => 3])->assertStatus(303);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($url.'/delete-confirmation?'.http_build_query($context))->assertOk()->assertSee('確認した内容')->assertSee('replies_pages=2');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $result = $this->delete($url, $context)->assertStatus(303);
        $this->assertStringContainsString('replies_pages=2', $result->headers->get('Location'));
        $this->get($result->headers->get('Location'))->assertOk()->assertDontSee('確認した内容')->assertSee('このメッセージは削除されました');
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($url, $context + ['body' => '旧下書き', 'expected_version' => 1])->assertStatus(409)->assertDontSee('旧下書き');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->delete($url, $context)->assertStatus(409)->assertDontSee('確認した内容');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_reply_permissions_hidden_channels_wrong_parent_and_tampered_ranges_are_rejected_before_input(): void
    {
        [$user, $channel, $parent] = $this->setupThread(true);
        $other = User::factory()->create();
        $reply = app(SaveMessage::class)->create($user, $channel, '秘密の返信', $parent);
        $foreign = app(SaveChannel::class)->create($user, ['name' => '別', 'is_private' => '0']);
        $foreignParent = app(SaveMessage::class)->create($user, $foreign, '別の親');
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $reply))->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->post($this->url($channel, $reply).'/replies', ['body' => ''])->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($foreign, $parent))->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent).'?replies_until='.$foreignParent->id)->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent).'?replies_pages[]=1')->assertStatus(422);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->post($this->url($channel, $parent).'/replies', ['body' => '保存しない', 'replies_pages' => 0, 'return_url' => 'https://example.com'])->assertStatus(303)->assertRedirect('/channels/'.$channel->id);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $channel->members()->attach($other);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->actingAs($other)->patch($this->messageUrl($channel, $reply), ['body' => '他人による有効な本文への変更', 'expected_version' => 1])->assertForbidden();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->messageUrl($channel, $reply).'/delete-confirmation')->assertForbidden();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->delete($this->messageUrl($channel, $reply))->assertForbidden();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $channel->members()->detach($other);
        foreach ([$this->url($channel, $parent), $this->messageUrl($channel, $reply).'/delete-confirmation'] as $url) {
            $snapshot = Message::orderBy('id')->get()->toArray();
            $this->get($url)->assertNotFound()->assertDontSee('秘密の返信');
            $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        }
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->post($this->url($channel, $parent).'/replies', ['body' => ''])->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($this->messageUrl($channel, $reply), ['body' => '旧下書き', 'expected_version' => 1])->assertNotFound()->assertDontSee('旧下書き');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $this->actingAs($user);
        $channel->members()->detach($user);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($this->messageUrl($channel, $reply), ['body' => '所属解除後の本人による変更', 'expected_version' => 1])->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->delete($this->messageUrl($channel, $reply))->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $channel->members()->attach($user);
        $channel->forceFill(['deleted_at' => now()])->save();
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent))->assertNotFound()->assertDontSee('秘密の返信');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->post($this->url($channel, $parent).'/replies', ['body' => '新規返信'])->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->delete($this->messageUrl($channel, $reply))->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->patch($this->messageUrl($channel, $reply), ['body' => 'チャンネル削除後の本人による変更', 'expected_version' => 1])->assertNotFound();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_seeder_creates_threads_only_for_first_conversations_and_keeps_existing_data_on_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parents = Message::whereNull('parent_message_id')->withCount('replies')->get();
        $this->assertGreaterThanOrEqual(2, $parents->where('replies_count', '>', 0)->count());
        $this->assertGreaterThanOrEqual(1, $parents->where('replies_count', '>=', 3)->count());
        $this->assertTrue($parents->whereNotNull('deleted_at')->contains(fn ($parent) => $parent->replies_count > 0));
        $private = Channel::where('is_private', true)->sole();
        $this->assertSame(0, $private->messages()->whereNotIn('user_id', $private->members()->pluck('users.id'))->count());
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_invalid_reply_ranges_show_feedback_and_invalid_get_preserves_safe_thread(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $this->get($this->url($channel, $parent).'?replies_pages=0')->assertStatus(422)->assertSee('表示条件が正しくありません')->assertSee('id="reply-body"', false);
        $response = $this->post($this->url($channel, $parent).'/replies', ['body' => '保存しない', 'replies_pages' => 0])->assertStatus(303);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('表示条件が正しくありません')->assertDontSee('保存しない');
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_guests_csrf_and_unprovided_methods_cannot_read_or_create_replies(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        auth()->logout();
        $this->get($this->url($channel, $parent))->assertRedirect('/login');
        $this->post($this->url($channel, $parent).'/replies', ['body' => '追加しない'])->assertRedirect('/login');
        $this->actingAs($user)->put($this->url($channel, $parent).'/replies', ['body' => '追加しない'])->assertStatus(405);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post($this->url($channel, $parent).'/replies', ['body' => '追加しない'])->assertRedirect('/login');
        $this->assertDatabaseCount('messages', 1);
    }

    #[DataProvider('changesAfterConflict')]
    public function test_reply_conflict_render_rechecks_current_channel_membership_deletion_and_version(string $change, int $status): void
    {
        [$user, $channel, $parent] = $this->setupThread(true);
        $reply = app(SaveMessage::class)->create($user, $channel, '非開示にする本文', $parent);
        $this->app->bind(SaveMessage::class, fn () => new class($change) extends SaveMessage
        {
            public function __construct(private string $change) {}

            public function update(User $user, Channel $channel, Message $message, string $body, int $expectedVersion): Message
            {
                $snapshot = $message->fresh();
                match ($this->change) {
                    'channel' => DB::table('channels')->where('id', $channel->id)->update(['deleted_at' => now()]),
                    'membership' => $channel->members()->detach($user->id),
                    'reply' => DB::table('messages')->where('id', $message->id)->update(['deleted_at' => now()]),
                    'version' => DB::table('messages')->where('id', $message->id)->update(['body' => '再取得した現在本文', 'version' => 3]),
                };
                throw new MessageConflict($snapshot);
            }
        });
        $response = $this->patch($this->messageUrl($channel, $reply), ['body' => '自分の旧下書き', 'expected_version' => 1])->assertStatus($status)->assertDontSee('非開示にする本文');
        if ($change === 'version') {
            $response->assertSee('再取得した現在本文')->assertSee('自分の旧下書き')->assertSee('name="expected_version" value="3"', false);
        } else {
            $response->assertDontSee('自分の旧下書き');
        }
    }

    public static function changesAfterConflict(): array
    {
        return [['channel', 404], ['membership', 404], ['reply', 409], ['version', 409]];
    }

    public function test_reply_save_failure_rolls_back_and_logs_identifiers_without_body_or_sql(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        Log::spy();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `messages`')) {
                throw new RuntimeException('秘密のSQL');
            }
        });
        $this->post($this->url($channel, $parent).'/replies', ['body' => '秘密の返信本文'])->assertStatus(500)->assertDontSee('秘密のSQL')->assertDontSee('秘密の返信本文');
        $this->assertDatabaseCount('messages', 1);
        Log::shouldHaveReceived('error')->with('Application operation failed', ['exception_class' => RuntimeException::class, 'route' => 'replies.store', 'channel_id' => $channel->id, 'message_id' => $parent->id])->once();
    }

    public function test_reply_save_revalidates_parent_and_membership_after_channel_lock(): void
    {
        [$user, $channel, $parent] = $this->setupThread(true);
        $action = app(SaveMessage::class);
        $parent->forceFill(['parent_message_id' => $action->create($user, $channel, '別親')->id])->save();
        try {
            $action->create($user, $channel, '追加しない', $parent);
            $this->fail('返信を親に保存しました');
        } catch (ModelNotFoundException $exception) {
            $this->assertDatabaseCount('messages', 2);
        }
        $channel->members()->detach($user);
        try {
            $action->create($user, $channel, '追加しない', $parent);
            $this->fail('所属解除後に保存しました');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }
    }

    public function test_thousand_character_reply_is_accepted_and_thread_editor_conflict_and_confirmation_escape_html(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $this->post($this->url($channel, $parent).'/replies', ['body' => str_repeat('あ', 1000)])->assertStatus(303);
        $reply = $parent->replies()->sole();
        $body = '<script>秘密</script>&';
        app(SaveMessage::class)->update($user, $channel, $reply, $body, 1);
        foreach ([$this->url($channel, $parent), $this->url($channel, $parent).'?edit='.$reply->id, $this->messageUrl($channel, $reply).'/delete-confirmation'] as $url) {
            $this->get($url)->assertOk()->assertSee($body)->assertDontSee('<script', false);
        }
        $this->patch($this->messageUrl($channel, $reply), ['body' => $body, 'expected_version' => 1])->assertStatus(409)->assertSee($body)->assertDontSee('<script', false);
    }

    public function test_existing_conversation_without_replies_is_not_supplemented_by_seeder(): void
    {
        $this->seed([AuthUserSeeder::class, ChannelSeeder::class]);
        $channel = Channel::where('name', '開発')->sole();
        $message = app(SaveMessage::class)->create($channel->creator, $channel, '既存会話');
        $this->seed(MessageSeeder::class);
        $this->assertSame([$message->id], $channel->messages()->pluck('id')->all());
    }

    public function test_wrong_thread_edit_and_reply_boundary_are_not_disclosed_and_normal_post_success_closes_thread(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $otherParent = app(SaveMessage::class)->create($user, $channel, '別の親');
        $reply = app(SaveMessage::class)->create($user, $channel, '別スレッド返信', $otherParent);
        $this->get($this->url($channel, $parent).'?edit='.$reply->id)->assertNotFound()->assertDontSee('別スレッド返信');
        $this->get($this->url($channel, $parent).'?replies_until='.$reply->id)->assertNotFound();
        $this->patch($this->messageUrl($channel, $reply), ['origin' => 'thread', 'thread' => $parent->id, 'body' => '保存しない', 'expected_version' => 1])->assertNotFound();
        $this->post('/channels/'.$channel->id.'/messages', ['origin' => 'thread', 'thread' => $parent->id, 'body' => '通常投稿成功'])->assertStatus(303)->assertRedirect('/channels/'.$channel->id);
    }

    #[DataProvider('missingOrUnrelatedTargets')]
    public function test_target_not_found_precedes_other_input_errors(string $method, string $entry, string $parameter, string $kind, mixed $pages): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $action = app(SaveMessage::class);
        $reply = $action->create($user, $channel, '現在スレッドの秘密', $parent);
        $otherParent = $action->create($user, $channel, '別の親');
        $otherReply = $action->create($user, $channel, '別スレッドの秘密', $otherParent);
        $foreign = app(SaveChannel::class)->create($user, ['name' => '別チャンネル', 'is_private' => '0']);
        $foreignPost = $action->create($user, $foreign, '別チャンネルの秘密');
        $id = match ($kind) {
            'missing' => 999999, 'foreign' => $foreignPost->id, 'other_reply' => $otherReply->id, 'reply' => $reply->id
        };
        $input = ['messages_pages' => $pages, 'replies_pages' => $pages, 'body' => str_repeat('秘', 1001), 'expected_version' => 1];
        $url = match ($entry) {
            'channel' => '/channels/'.$channel->id,
            'thread' => $this->url($channel, $parent),
            'reply' => $this->url($channel, $parent).'/replies',
            'store' => '/channels/'.$channel->id.'/messages',
            'edit', 'delete' => $this->messageUrl($channel, $reply),
            'confirmation' => $this->messageUrl($channel, $reply).'/delete-confirmation',
        };
        if ($parameter === 'channel_path') {
            $url = str_replace('/channels/'.$channel->id, '/channels/'.$id, $url);
        } elseif ($parameter === 'thread_path') {
            $url = str_replace('/threads/'.$parent->id, '/threads/'.$id, $url);
        } elseif ($parameter === 'message_path') {
            $url = str_replace('/messages/'.$reply->id, '/messages/'.$id, $url);
        } else {
            $input[$parameter] = $id;
        }
        if (in_array($entry, ['store', 'edit', 'delete', 'confirmation'], true)) {
            $input += ['origin' => 'thread', 'thread' => $parent->id];
        }
        $snapshot = Message::orderBy('id')->get()->toArray();
        $response = $method === 'GET' ? $this->get($url.'?'.http_build_query($input)) : $this->{strtolower($method)}($url, $input);
        $response->assertNotFound()->assertDontSee('現在スレッドの秘密')->assertDontSee('別スレッドの秘密')->assertDontSee('別チャンネルの秘密');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public static function missingOrUnrelatedTargets(): array
    {
        return [
            ['GET', 'thread', 'channel_path', 'missing', 0],
            ['GET', 'thread', 'thread_path', 'missing', [0]],
            ['GET', 'thread', 'thread_path', 'reply', 0],
            ['GET', 'channel', 'edit', 'missing', 0],
            ['GET', 'thread', 'edit', 'other_reply', [0]],
            ['GET', 'thread', 'focus', 'missing', 0],
            ['GET', 'thread', 'focus', 'other_reply', [0]],
            ['GET', 'thread', 'messages_until', 'foreign', 0],
            ['GET', 'thread', 'replies_until', 'missing', [0]],
            ['GET', 'thread', 'replies_until', 'other_reply', 0],
            ['GET', 'confirmation', 'replies_until', 'other_reply', [0]],
            ['GET', 'confirmation', 'message_path', 'missing', 0],
            ['POST', 'reply', 'thread_path', 'reply', [0]],
            ['POST', 'reply', 'thread_path', 'foreign', 0],
            ['POST', 'reply', 'replies_until', 'other_reply', 0],
            ['POST', 'store', 'thread', 'missing', [0]],
            ['POST', 'store', 'messages_until', 'foreign', 0],
            ['PATCH', 'edit', 'message_path', 'missing', 0],
            ['PATCH', 'edit', 'thread', 'foreign', [0]],
            ['PATCH', 'edit', 'replies_until', 'other_reply', 0],
            ['DELETE', 'delete', 'replies_until', 'other_reply', [0]],
            ['DELETE', 'delete', 'message_path', 'foreign', 0],
        ];
    }

    #[DataProvider('malformedTargetIds')]
    public function test_malformed_ids_keep_input_error_contract_without_database_changes(mixed $id): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $snapshot = Message::orderBy('id')->get()->toArray();
        foreach (['thread', 'edit', 'focus', 'messages_until', 'replies_until'] as $parameter) {
            $input = [$parameter => $id, 'messages_pages' => 0];
            $this->get($this->url($channel, $parent).'?'.http_build_query($input))->assertStatus(422);
            $this->post($this->url($channel, $parent).'/replies', $input + ['body' => ''])->assertStatus(303);
        }
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public static function malformedTargetIds(): array
    {
        return [[['1']], [''], ['1.5'], ['999999999999999999999999999'], ['-1']];
    }

    #[DataProvider('invalidPageValues')]
    public function test_valid_targets_keep_page_input_errors_and_rejected_requests_do_not_write(mixed $page): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $reply = app(SaveMessage::class)->create($user, $channel, '既存返信', $parent);
        $input = ['origin' => 'thread', 'thread' => $parent->id, 'messages_until' => $parent->id, 'replies_until' => $reply->id,
            'replies_pages' => $page, 'body' => '', 'expected_version' => 1];
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent).'?'.http_build_query($input))->assertStatus(422);
        $this->get($this->messageUrl($channel, $reply).'/delete-confirmation?'.http_build_query($input))->assertStatus(422);
        $this->post($this->url($channel, $parent).'/replies', $input)->assertStatus(303);
        $this->post('/channels/'.$channel->id.'/messages', $input)->assertStatus(303);
        $this->patch($this->messageUrl($channel, $reply), $input)->assertStatus(303);
        $this->delete($this->messageUrl($channel, $reply), $input)->assertStatus(303);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public static function invalidPageValues(): array
    {
        return [[0], [[0]]];
    }

    #[DataProvider('inaccessibleChannelStates')]
    public function test_channel_visibility_precedes_malformed_input_for_all_thread_entries(string $state): void
    {
        [$user, $channel, $parent] = $this->setupThread(true);
        $reply = app(SaveMessage::class)->create($user, $channel, '開示しない返信', $parent);
        if ($state === 'removed') {
            $channel->members()->detach($user);
        } else {
            $channel->forceFill(['deleted_at' => now()])->save();
        }
        $snapshot = Message::orderBy('id')->get()->toArray();
        $input = ['origin' => 'thread', 'thread' => ['不正'], 'replies_until' => 999999, 'messages_pages' => 0, 'body' => '', 'expected_version' => 0];
        $urls = [
            ['GET', $this->url($channel, $parent)],
            ['GET', $this->messageUrl($channel, $reply).'/delete-confirmation'],
            ['POST', $this->url($channel, $parent).'/replies'],
            ['POST', '/channels/'.$channel->id.'/messages'],
            ['PATCH', $this->messageUrl($channel, $reply)],
            ['DELETE', $this->messageUrl($channel, $reply)],
        ];
        foreach ($urls as [$method, $url]) {
            $response = $method === 'GET' ? $this->get($url.'?'.http_build_query($input)) : $this->{strtolower($method)}($url, $input);
            $response->assertNotFound()->assertDontSee('開示しない返信')->assertDontSee('親の本文');
        }
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public static function inaccessibleChannelStates(): array
    {
        return [['removed'], ['deleted']];
    }

    public function test_editor_author_and_deleted_state_remain_prior_to_page_validation(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $other = User::factory()->create();
        $reply = app(SaveMessage::class)->create($other, $channel, '他人の返信', $parent);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent).'?edit='.$reply->id.'&replies_pages=0')->assertForbidden();
        $this->patch($this->messageUrl($channel, $reply), ['body' => '', 'expected_version' => 1, 'replies_pages' => 0])->assertForbidden();
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        app(SaveMessage::class)->delete($other, $channel, $reply);
        $this->actingAs($other);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->get($this->url($channel, $parent).'?edit='.$reply->id.'&replies_pages=0')->assertStatus(409)->assertDontSee('他人の返信');
        $this->patch($this->messageUrl($channel, $reply), ['body' => '', 'expected_version' => 1, 'replies_pages' => 0])->assertStatus(409)->assertDontSee('他人の返信');
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_twenty_one_posts_and_replies_expand_independently_with_exact_dom_rows_and_read_position(): void
    {
        [$user, $channel, $parent, $posts, $replies] = $this->historyFixture();
        $initial = $this->get($this->url($channel, $parent))->assertOk();
        $this->assertSame($posts->slice(11)->pluck('id')->all(), $this->historyIds($initial, 'messages', $parent));
        $this->assertSame($replies->slice(11)->pluck('id')->all(), $this->historyIds($initial, 'replies', $parent));
        $boundary = ['messages_until' => $posts->last()->id, 'messages_pages' => 1, 'replies_until' => $replies->last()->id, 'replies_pages' => 2];
        $replyUrl = $this->historyLink($initial, 'replies', $boundary, 'message-'.$replies[11]->id);
        $replyTwenty = $this->get($replyUrl)->assertOk();
        $this->assertSame($posts->slice(11)->pluck('id')->all(), $this->historyIds($replyTwenty, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($replyTwenty, 'replies', $parent));
        $allReplyUrl = $this->historyLink($replyTwenty, 'replies', array_replace($boundary, ['replies_pages' => 3]), 'message-'.$replies[1]->id);
        $allReplies = $this->get($allReplyUrl)->assertOk();
        $this->assertSame($posts->slice(11)->pluck('id')->all(), $this->historyIds($allReplies, 'messages', $parent));
        $this->assertSame($replies->pluck('id')->all(), $this->historyIds($allReplies, 'replies', $parent));
        $postUrl = $this->historyLink($replyTwenty, 'messages', array_replace($boundary, ['messages_pages' => 2]), 'message-'.$posts[11]->id);
        $bothTwenty = $this->get($postUrl)->assertOk();
        $this->assertSame($posts->slice(1)->pluck('id')->all(), $this->historyIds($bothTwenty, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($bothTwenty, 'replies', $parent));
        $allPostUrl = $this->historyLink($bothTwenty, 'messages', array_replace($boundary, ['messages_pages' => 3]), 'message-'.$posts[1]->id);
        $allPosts = $this->get($allPostUrl)->assertOk();
        $this->assertSame($posts->pluck('id')->all(), $this->historyIds($allPosts, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($allPosts, 'replies', $parent));
    }

    public function test_reply_success_keeps_twenty_posts_but_resets_only_replies_to_latest_ten(): void
    {
        [$user, $channel, $parent, $posts, $replies] = $this->historyFixture();
        $context = ['origin' => 'thread', 'thread' => $parent->id, 'messages_until' => $posts->last()->id, 'messages_pages' => 2,
            'replies_until' => $replies->last()->id, 'replies_pages' => 2];
        $before = $this->get($this->url($channel, $parent).'?'.http_build_query($context))->assertOk();
        $this->assertSame($posts->slice(1)->pluck('id')->all(), $this->historyIds($before, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($before, 'replies', $parent));
        $result = $this->post($this->url($channel, $parent).'/replies', $context + ['body' => '対象返信22'])->assertStatus(303);
        $latest = $parent->replies()->where('body', '対象返信22')->sole();
        $page = $this->get($result->headers->get('Location'))->assertOk()->assertSee('返信22件');
        $this->assertSame('返信22件', trim($this->historyDom($page)->query($this->historyScope('replies').'/div[@class="thread-count"]')->item(0)->textContent));
        $this->assertSame($posts->slice(1)->pluck('id')->all(), $this->historyIds($page, 'messages', $parent));
        $this->assertSame([...$replies->slice(12)->pluck('id')->all(), $latest->id], $this->historyIds($page, 'replies', $parent));
    }

    public function test_each_composer_error_keeps_twenty_rows_on_both_sides_and_only_restores_submitted_field(): void
    {
        [$user, $channel, $parent, $posts, $replies] = $this->historyFixture();
        $context = ['origin' => 'thread', 'thread' => $parent->id, 'messages_until' => $posts->last()->id, 'messages_pages' => 2,
            'replies_until' => $replies->last()->id, 'replies_pages' => 2];
        $draft = str_repeat('長', 1001);
        foreach (['message', 'reply'] as $form) {
            $snapshot = Message::orderBy('id')->get()->toArray();
            $url = $form === 'message' ? '/channels/'.$channel->id.'/messages' : $this->url($channel, $parent).'/replies';
            $result = $this->post($url, $context + ['body' => $draft])->assertStatus(303);
            parse_str(parse_url($result->headers->get('Location'), PHP_URL_QUERY), $parameters);
            $this->assertEquals(array_diff_key($context, ['origin' => true, 'thread' => true]), $parameters);
            $page = $this->get($result->headers->get('Location'))->assertOk();
            $this->assertSame($posts->slice(1)->pluck('id')->all(), $this->historyIds($page, 'messages', $parent));
            $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($page, 'replies', $parent));
            $this->assertSame($draft, $this->textarea($page, $form.'-body'));
            $other = $form === 'message' ? 'reply' : 'message';
            $this->assertSame('', $this->textarea($page, $other.'-body'));
            $dom = $this->historyDom($page);
            $this->assertSame(1, $dom->query('//*[@id="'.$form.'-error" and @role="alert"]')->length);
            $this->assertSame(0, $dom->query('//*[@id="'.$other.'-error"]')->length);
            $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        }
    }

    public function test_existing_history_boundaries_exclude_new_posts_and_replies_while_total_reply_count_grows(): void
    {
        [$user, $channel, $parent, $posts, $replies] = $this->historyFixture();
        $context = ['messages_until' => $posts->last()->id, 'messages_pages' => 2, 'replies_until' => $replies->last()->id, 'replies_pages' => 2];
        $before = $this->get($this->url($channel, $parent).'?'.http_build_query($context))->assertOk();
        $this->assertSame($posts->slice(1)->pluck('id')->all(), $this->historyIds($before, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($before, 'replies', $parent));
        app(SaveMessage::class)->create($user, $channel, '範囲外の新着通常投稿');
        app(SaveMessage::class)->create($user, $channel, '範囲外の新着返信', $parent);
        $postUrl = $this->historyLink($before, 'messages', array_replace($context, ['messages_pages' => 3]), 'message-'.$posts[1]->id);
        $afterPosts = $this->get($postUrl)->assertOk()->assertSee('返信22件');
        $this->assertSame('返信22件', trim($this->historyDom($afterPosts)->query($this->historyScope('replies').'/div[@class="thread-count"]')->item(0)->textContent));
        $this->assertSame($posts->pluck('id')->all(), $this->historyIds($afterPosts, 'messages', $parent));
        $this->assertSame($replies->slice(1)->pluck('id')->all(), $this->historyIds($afterPosts, 'replies', $parent));
        $replyUrl = $this->historyLink($afterPosts, 'replies', array_replace($context, ['messages_pages' => 3, 'replies_pages' => 3]), 'message-'.$replies[1]->id);
        $allOldRows = $this->get($replyUrl)->assertOk()->assertSee('返信22件');
        $this->assertSame('返信22件', trim($this->historyDom($allOldRows)->query($this->historyScope('replies').'/div[@class="thread-count"]')->item(0)->textContent));
        $this->assertSame($posts->pluck('id')->all(), $this->historyIds($allOldRows, 'messages', $parent));
        $this->assertSame($replies->pluck('id')->all(), $this->historyIds($allOldRows, 'replies', $parent));
    }

    public function test_reply_ranges_use_timestamp_then_id_for_latest_selection_history_and_equal_timestamp_order(): void
    {
        [$user, $channel, $parent] = $this->setupThread();
        $start = CarbonImmutable::parse('2026-10-28 00:00:00', 'UTC');
        $replies = collect(range(1, 11))->map(fn ($number) => Message::forceCreate([
            'channel_id' => $channel->id, 'user_id' => $user->id, 'parent_message_id' => $parent->id,
            'body' => '並び確認'.$number, 'version' => 1, 'created_at' => $start->addMinutes($number),
        ]));
        // 最小IDを最も新しくし、次の2件は同時刻にしてID順も独立して確認します。
        $replies[0]->forceFill(['created_at' => $start->addMinutes(12)])->save();
        $replies[9]->forceFill(['created_at' => $start->addMinutes(11)])->save();
        $page = $this->get($this->url($channel, $parent))->assertOk();
        $expected = [...$replies->slice(2)->pluck('id')->all(), $replies[0]->id];
        $this->assertSame($expected, $this->historyIds($page, 'replies', $parent));
        $url = $this->historyLink($page, 'replies', ['messages_until' => $parent->id, 'messages_pages' => 1, 'replies_until' => $replies[0]->id, 'replies_pages' => 2], 'message-'.$replies[2]->id);
        $older = $this->get($url)->assertOk();
        $this->assertSame([...$replies->slice(1)->pluck('id')->all(), $replies[0]->id], $this->historyIds($older, 'replies', $parent));
    }

    #[DataProvider('replyAuthorizationOrder')]
    public function test_reply_relationship_precedes_author_and_deleted_checks_but_page_validation_does_not(string $operation, string $relation, bool $ownedDeleted, int $status): void
    {
        [$viewer, $channel, $parent] = $this->setupThread();
        $author = $ownedDeleted ? $viewer : User::factory()->create();
        $otherParent = app(SaveMessage::class)->create($viewer, $channel, '別の親');
        $reply = app(SaveMessage::class)->create($author, $channel, '非開示の返信本文', $parent);
        if ($ownedDeleted) {
            app(SaveMessage::class)->delete($author, $channel, $reply);
        }
        $thread = match ($relation) {
            'correct' => $parent->id, 'wrong' => $otherParent->id, 'missing' => 999999,
        };
        foreach ([1, 0] as $page) {
            $input = ['origin' => 'thread', 'thread' => $thread, 'replies_pages' => $page,
                'body' => '有効な変更用本文', 'expected_version' => 1];
            $snapshot = Message::orderBy('id')->get()->toArray();
            $url = $this->messageUrl($channel, $reply);
            $response = match ($operation) {
                'edit_view' => $this->get($this->url($channel, $parent).'?'.http_build_query($input + ['edit' => $reply->id])),
                'patch' => $this->patch($url, $input),
                'delete' => $this->delete($url, $input),
                'confirmation' => $this->get($url.'/delete-confirmation?'.http_build_query($input)),
            };
            $response->assertStatus($status)->assertDontSee('非開示の返信本文')->assertDontSee('有効な変更用本文');
            $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        }
    }

    public static function replyAuthorizationOrder(): array
    {
        $cases = [];
        foreach (['edit_view', 'patch', 'delete', 'confirmation'] as $operation) {
            foreach (['wrong' => 404, 'missing' => 404, 'correct' => 403] as $relation => $status) {
                $cases['other_'.$operation.'_'.$relation] = [$operation, $relation, false, $status];
            }
        }
        foreach (['patch', 'delete', 'confirmation'] as $operation) {
            foreach (['wrong' => 404, 'correct' => 409] as $relation => $status) {
                $cases['deleted_'.$operation.'_'.$relation] = [$operation, $relation, true, $status];
            }
        }

        return $cases;
    }
}
