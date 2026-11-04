<?php

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveMember
{
    public function add(User $actor, Channel $channel, int $userId): void
    {
        $this->change($actor, $channel, function (Channel $current) use ($userId) {
            if (! User::whereKey($userId)->exists()) {
                throw ValidationException::withMessages(['user_id' => '追加するメンバーを選択してください']);
            }
            $current->members()->syncWithoutDetaching([$userId]);
        });
    }

    public function remove(User $actor, Channel $channel, int $userId): void
    {
        $this->change($actor, $channel, function (Channel $current) use ($userId) {
            if (! User::whereKey($userId)->exists()) {
                throw ValidationException::withMessages(['member' => '対象のメンバーを選択してください']);
            }
            $current->members()->detach($userId);
        }, 'removeMember', [$userId]);
    }

    private function change(User $actor, Channel $channel, callable $operation, string $ability = 'manageMembers', array $arguments = []): void
    {
        DB::transaction(function () use ($actor, $channel, $operation, $ability, $arguments) {
            $current = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($ability, [$current, ...$arguments]);
            $operation($current);
        });
    }
}
