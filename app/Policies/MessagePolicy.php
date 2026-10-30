<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
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

    public function delete(User $user, Message $message): Response
    {
        return $this->update($user, $message);
    }
}
