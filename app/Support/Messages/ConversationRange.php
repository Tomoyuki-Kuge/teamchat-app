<?php

namespace App\Support\Messages;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class ConversationRange
{
    public readonly Collection $messages;

    public readonly bool $hasOlder;

    public readonly array $parameters;

    private function __construct(private Channel $channel, ?Message $boundary, int $pages)
    {
        $query = $channel->messages()->whereNull('parent_message_id');
        if ($boundary) {
            $query->where(function (Builder $query) use ($boundary) {
                $query->where('created_at', '<', $boundary->created_at)
                    ->orWhere(fn (Builder $query) => $query->where('created_at', $boundary->created_at)->where('id', '<=', $boundary->id));
            });
        }
        // 巨大なページ数でも乗算をオーバーフローさせず、存在する件数までにします。
        $total = (clone $query)->count();
        $limit = $pages > intdiv(PHP_INT_MAX, 10) ? $total : min($total, $pages * 10);
        $this->messages = $query->with('author')->withCount('replies')
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        $this->hasOlder = $total > $limit;
        $this->parameters = $boundary ? ['messages_until' => $boundary->id, 'messages_pages' => $pages] : [];
    }

    public static function from(Channel $channel, array $input): self
    {
        $rules = ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'regex:/^[0-9]+$/D'];
        Validator::make($input, [
            'messages_until' => $rules, 'messages_pages' => $rules, 'focus' => $rules, 'edit' => $rules,
            'origin' => ['sometimes', 'required', 'in:channel'],
        ], ['*' => '表示条件が正しくありません'])->validate();

        $posts = $channel->messages()->whereNull('parent_message_id');
        $focus = isset($input['focus']) ? (clone $posts)->findOrFail($input['focus']) : null;
        $boundary = isset($input['messages_until']) ? (clone $posts)->findOrFail($input['messages_until'])
            : ($focus ?? (clone $posts)->orderByDesc('created_at')->orderByDesc('id')->first());

        return new self($channel, $boundary, (int) ($input['messages_pages'] ?? 1));
    }

    public function url(array $extra = [], ?int $anchor = null): string
    {
        return route('channels.show', ['channel' => $this->channel->id] + $extra + $this->parameters)
            .($anchor ? '#message-'.$anchor : '');
    }

    public function olderUrl(): string
    {
        return $this->url(['messages_pages' => $this->parameters['messages_pages'] + 1], $this->messages->first()?->id);
    }
}
