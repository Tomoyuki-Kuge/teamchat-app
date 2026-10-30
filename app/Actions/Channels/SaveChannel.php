<?php

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveChannel
{
    public function create(User $user, array $input): Channel
    {
        return $this->withNameConflict(function () use ($user, $input) {
            return DB::transaction(function () use ($user, $input) {
                $channel = new Channel($this->details($input) + ['is_private' => $input['is_private']]);
                $channel->creator()->associate($user);
                $channel->save();
                $channel->members()->attach($user->id);

                return $channel;
            });
        });
    }

    public function update(User $user, Channel $channel, array $input): Channel
    {
        return $this->withNameConflict(function () use ($user, $channel, $input) {
            return DB::transaction(function () use ($user, $channel, $input) {
                $current = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('update', $current);
                $current->fill($this->details($input))->save();

                return $current;
            });
        });
    }

    public function delete(User $user, Channel $channel, string $confirmationName): void
    {
        DB::transaction(function () use ($user, $channel, $confirmationName) {
            $current = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('delete', $current);
            if ($current->name !== $confirmationName) {
                throw ValidationException::withMessages(['confirmation_name' => 'チャンネル名が一致しません']);
            }
            // 会話・所属は保持し、チャンネルの状態だけで利用を終了します。
            $current->deleted_at = now('UTC');
            $current->save();
        });
    }

    private function details(array $input): array
    {
        return ['name' => $input['name'], 'description' => $input['description'] ?? null];
    }

    private function withNameConflict(callable $operation): Channel
    {
        try {
            return $operation();
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'channels_active_name_unique')) {
                throw $exception;
            }
            throw ValidationException::withMessages(['name' => '使用できない名前です']);
        }
    }
}
