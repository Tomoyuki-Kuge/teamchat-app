<?php

namespace App\Actions\Messages;

use App\Exceptions\MessageConflict;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveMessage
{
    public function create(User $user, Channel $channel, string $body): Message
    {
        return DB::transaction(function () use ($user, $channel, $body) {
            $current = $this->lockChannel($user, $channel);
            $message = new Message(['body' => $body]);
            $message->channel()->associate($current);
            $message->author()->associate($user);
            $message->version = 1;
            $message->save();

            return $message;
        });
    }

    public function update(User $user, Channel $channel, Message $message, string $body, int $expectedVersion): Message
    {
        return DB::transaction(function () use ($user, $channel, $message, $body, $expectedVersion) {
            $current = $this->lockMessage($user, $channel, $message, 'update');
            if ($current->version !== $expectedVersion) {
                throw new MessageConflict($current);
            }
            $current->body = $body;
            $current->edited_at = now('UTC');
            $current->version++;
            $current->save();

            return $current;
        });
    }

    public function delete(User $user, Channel $channel, Message $message): void
    {
        DB::transaction(function () use ($user, $channel, $message) {
            $current = $this->lockMessage($user, $channel, $message, 'delete');
            $current->deleted_at = now('UTC');
            $current->save();
        });
    }

    private function lockChannel(User $user, Channel $channel): Channel
    {
        $current = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($user)->authorize('view', $current);

        return $current;
    }

    private function lockMessage(User $user, Channel $channel, Message $message, string $ability): Message
    {
        $currentChannel = $this->lockChannel($user, $channel);
        $current = $currentChannel->messages()->whereKey($message->id)->lockForUpdate()->firstOrFail();
        $current->setRelation('channel', $currentChannel);
        Gate::forUser($user)->authorize($ability, $current);
        abort_if($current->deleted_at !== null, 409, 'このメッセージは削除されています');

        return $current;
    }
}
