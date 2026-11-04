<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    public function view(User $user, Channel $channel): Response
    {
        return $channel->deleted_at === null && (! $channel->is_private || $channel->members()->where('users.id', $user->id)->exists())
            ? Response::allow()
            : Response::denyAsNotFound('対象が見つかりません');
    }

    public function update(User $user, Channel $channel): Response
    {
        $visibility = $this->view($user, $channel);
        if ($visibility->denied()) {
            return $visibility;
        }

        return $channel->creator_id === $user->id ? Response::allow() : Response::deny('この操作はできません');
    }

    public function manageMembers(User $user, Channel $channel): Response
    {
        $visibility = $this->view($user, $channel);
        if ($visibility->denied()) {
            return $visibility;
        }

        return $channel->is_private && $channel->creator_id === $user->id
            ? Response::allow() : Response::deny('この操作はできません');
    }

    public function removeMember(User $user, Channel $channel, int $userId): Response
    {
        $management = $this->manageMembers($user, $channel);
        if ($management->denied()) {
            return $management;
        }

        return $channel->creator_id === $userId
            ? Response::deny('作成者は外せません') : Response::allow();
    }

    public function delete(User $user, Channel $channel): Response
    {
        return $this->update($user, $channel);
    }
}
