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

    public function delete(User $user, Channel $channel): Response
    {
        return $this->update($user, $channel);
    }
}
