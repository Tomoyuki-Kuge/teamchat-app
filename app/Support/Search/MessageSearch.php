<?php

namespace App\Support\Search;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MessageSearch
{
    public function find(User $user, array $words, string $sort, int $page, ?int $until): array
    {
        $query = Message::query()->whereNull('messages.deleted_at')
            ->whereHas('channel', fn (Builder $channels) => $channels->visibleTo($user));
        if ($until !== null) {
            $query->where('messages.id', '<=', $until);
        }
        $ascending = $sort === 'oldest';
        $operator = $ascending ? '>' : '<';
        $direction = $ascending ? 'asc' : 'desc';
        $total = 0;
        $maximum = 0;
        $selected = new Collection;
        $boundary = null;
        // ページを件数へ乗算せず、巨大な有効ページも整数のまま比較します。
        do {
            $batch = (clone $query)->when($boundary, function (Builder $query) use ($boundary, $operator) {
                $query->where(fn (Builder $query) => $query->where('created_at', $operator, $boundary['created_at'])
                    ->orWhere(fn (Builder $query) => $query->where('created_at', $boundary['created_at'])->where('id', $operator, $boundary['id'])));
            })->orderBy('created_at', $direction)->orderBy('id', $direction)->limit(100)->get();
            foreach ($batch as $message) {
                $body = SearchText::normalize($message->body);
                foreach ($words as $word) {
                    if (! str_contains($body, $word)) {
                        continue 2;
                    }
                }
                if (intdiv($total, 10) + 1 === $page) {
                    $selected->push($message);
                }
                $total++;
                $maximum = max($maximum, $message->id);
            }
            $batchCount = $batch->count();
            $last = $batch->last();
            $boundary = $last ? ['created_at' => $last->getRawOriginal('created_at'), 'id' => $last->id] : null;
            // 次の取得時に旧バッチを重ねて保持しないよう、境界はスカラーだけにします。
            unset($batch, $last, $message, $body);
        } while ($batchCount === 100);
        $selected->load('channel', 'author');

        return ['results' => $selected, 'total' => $total, 'until' => $until ?? $maximum,
            'page' => $page, 'lastPage' => max(1, intdiv(max(0, $total - 1), 10) + 1)];
    }
}
