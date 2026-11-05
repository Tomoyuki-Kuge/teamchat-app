<?php

use App\Actions\Channels\SaveChannel;
use App\Actions\Channels\SaveMember;
use App\Actions\Messages\SaveMessage;
use App\Exceptions\MessageConflict;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function emitEvent(string $event, array $details = []): void
{
    fwrite(STDOUT, json_encode(['event' => $event] + $details, JSON_THROW_ON_ERROR)."\n");
    fflush(STDOUT);
}

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.example');
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('testing') || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== 'testing') {
        throw new RuntimeException('Testing database required');
    }
    $testingConnection = true;
    if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'testing') {
        throw new RuntimeException('Testing database required');
    }
    $input = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $owner = User::findOrFail($input['owner']);
    $author = User::findOrFail($input['author']);
    $channel = Channel::findOrFail($input['channel']);
    $parent = Message::findOrFail($input['parent']);
    $target = Message::findOrFail($input['target']);
    $connection = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    if ($input['role'] === 'leader') {
        DB::beginTransaction();
        match ($input['scenario']) {
            'membership' => app(SaveMember::class)->remove($owner, $channel, $author->id),
            'channel' => app(SaveChannel::class)->delete($owner, $channel, $channel->name),
            'version' => app(SaveMessage::class)->update($author, $channel, $target, '先行編集の本文', 1),
        };
        // Actionの内側transaction終了後も、外側transactionで実際の行ロックを保持します。
        emitEvent('locked', ['connection' => $connection, 'transaction_level' => DB::transactionLevel()]);
        if (trim(fgets(STDIN)) !== 'commit') {
            throw new RuntimeException('Commit instruction required');
        }
        DB::commit();
        emitEvent('committed');
        emitEvent('result', ['outcome' => 'saved']);
    } else {
        emitEvent('started', ['connection' => $connection, 'transaction_level' => DB::transactionLevel()]);
        if ($input['scenario'] === 'version') {
            app(SaveMessage::class)->update($author, $channel, $target, '後続編集の本文', 1);
        } else {
            app(SaveMessage::class)->create($author, $channel, '拒否される新規返信', $parent);
        }
        emitEvent('result', ['outcome' => 'saved']);
    }
} catch (AuthorizationException $exception) {
    emitEvent('result', ['outcome' => 'authorization', 'status' => $exception->status()]);
} catch (MessageConflict $exception) {
    emitEvent('result', ['outcome' => 'conflict', 'version' => $exception->current->version, 'body' => $exception->current->body]);
} catch (Throwable $exception) {
    // 子プロセスの診断にもSQL・認証情報を出さず、例外クラスだけを返します。
    emitEvent('error', ['exception' => $exception::class]);
    $failed = true;
} finally {
    if (isset($testingConnection)) {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::disconnect();
    }
}

exit(isset($failed) ? 1 : 0);
