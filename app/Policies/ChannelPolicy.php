<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * チャンネルの閲覧・編集・削除とメンバー管理の権限を判定する。
 */
class ChannelPolicy
{
    /**
     * 未削除で公開または本人が所属するチャンネルの閲覧を許可する。
     */
    public function view(User $user, Channel $channel): Response
    {
        return $channel->deleted_at === null && (! $channel->is_private || $channel->members()->where('users.id', $user->id)->exists())
            ? Response::allow()
            : Response::denyAsNotFound('対象が見つかりません');
    }

    /**
     * 閲覧を確認したうえで、作成者にチャンネル編集を許可する。
     */
    public function update(User $user, Channel $channel): Response
    {
        $visibility = $this->view($user, $channel);
        if ($visibility->denied()) {
            return $visibility;
        }

        return $channel->creator_id === $user->id ? Response::allow() : Response::deny('この操作はできません');
    }

    /**
     * 閲覧を確認したうえで、プライベートチャンネルの作成者にメンバー管理を許可する。
     */
    public function manageMembers(User $user, Channel $channel): Response
    {
        $visibility = $this->view($user, $channel);
        if ($visibility->denied()) {
            return $visibility;
        }

        return $channel->is_private && $channel->creator_id === $user->id
            ? Response::allow() : Response::deny('この操作はできません');
    }

    /**
     * メンバー管理権限を確認し、作成者以外の所属解除を許可する。
     */
    public function removeMember(User $user, Channel $channel, int $userId): Response
    {
        $management = $this->manageMembers($user, $channel);
        if ($management->denied()) {
            return $management;
        }

        return $channel->creator_id === $userId
            ? Response::deny('作成者は外せません') : Response::allow();
    }

    /**
     * 編集と同じ権限条件でチャンネル削除の可否を判定する。
     */
    public function delete(User $user, Channel $channel): Response
    {
        return $this->update($user, $channel);
    }
}
