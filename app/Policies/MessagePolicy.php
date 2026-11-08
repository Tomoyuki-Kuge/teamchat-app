<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * メッセージの編集・削除に関する権限を判定する。
 */
class MessagePolicy
{
    /**
     * チャンネルを閲覧できる投稿者本人に編集を許可する。
     */
    public function update(User $user, Message $message): Response
    {
        $visibility = (new ChannelPolicy)->view($user, $message->channel);
        if ($visibility->denied()) {
            return $visibility;
        }

        return $message->user_id === $user->id
            ? Response::allow()
            : Response::deny('この操作はできません');
    }

    /**
     * 編集と同じ条件でメッセージの削除可否を判定する。
     */
    public function delete(User $user, Message $message): Response
    {
        return $this->update($user, $message);
    }
}
