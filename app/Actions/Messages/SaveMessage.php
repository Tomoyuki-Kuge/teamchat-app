<?php

namespace App\Actions\Messages;

use App\Exceptions\MessageConflict;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * 通常投稿・返信の作成、本文編集、個別削除を保存する。
 */
class SaveMessage
{
    /**
     * 現在の権限と返信先を確認し、通常投稿または返信を保存する。
     */
    public function create(User $user, Channel $channel, string $body, ?Message $parent = null): Message
    {
        return DB::transaction(function () use ($user, $channel, $body, $parent) {
            $current = $this->lockChannel($user, $channel);
            $parent = $parent ? $current->messages()->whereNull('parent_message_id')->whereKey($parent->id)->lockForUpdate()->firstOrFail() : null;
            $message = new Message(['body' => $body]);
            $message->parent_message_id = $parent?->id;
            $message->channel()->associate($current);
            $message->author()->associate($user);
            $message->version = 1;
            $message->save();

            return $message;
        });
    }

    /**
     * 版の一致を確認し、本文・編集日時・版をまとめて更新する。
     */
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

    /**
     * 本人の未削除メッセージに削除日時を設定する。
     */
    public function delete(User $user, Channel $channel, Message $message): void
    {
        DB::transaction(function () use ($user, $channel, $message) {
            $current = $this->lockMessage($user, $channel, $message, 'delete');
            $current->deleted_at = now('UTC');
            $current->save();
        });
    }

    /**
     * チャンネルをロックして再取得し、現在の閲覧権限を確認する。
     */
    private function lockChannel(User $user, Channel $channel): Channel
    {
        $current = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($user)->authorize('view', $current);

        return $current;
    }

    /**
     * チャンネルと投稿を順にロックし、本人の操作権限と未削除を確認する。
     */
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
