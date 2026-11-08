<?php

namespace App\Support\Messages;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 会話の表示範囲の取得・保持と内部URL生成を担う。
 */
class ConversationRange
{
    public readonly Collection $messages;

    public readonly bool $hasOlder;

    public readonly array $parameters;

    /**
     * 指定範囲の通常投稿または返信を取得し、表示順と引き継ぎ情報を整える。
     */
    private function __construct(private Channel $channel, ?Message $boundary, int $pages, public readonly ?Message $thread = null, public readonly ?self $replies = null, private string $prefix = 'messages')
    {
        $query = $channel->messages()->where('parent_message_id', $prefix === 'replies' ? $thread->id : null);
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
        $this->parameters = $boundary ? [$prefix.'_until' => $boundary->id, $prefix.'_pages' => $pages] : [];
    }

    /**
     * 存在・所属の確認を入力エラーより先に行います。形式不正のIDは検索せず、後の入力検証へ渡します。
     *
     * @return array<string, Message|null>
     */
    public static function resolveTargets(Channel $channel, array $input, ?Message $thread = null, ?Message $target = null): array
    {
        Gate::authorize('view', $channel);
        $resolved = [];
        foreach (['thread', 'edit', 'focus', 'messages_until', 'replies_until'] as $key) {
            if (! array_key_exists($key, $input) || Validator::make([$key => $input[$key]], [$key => self::idRules()])->fails()) {
                continue;
            }
            $query = $channel->messages();
            if (in_array($key, ['thread', 'messages_until'], true)) {
                $query->whereNull('parent_message_id');
            } elseif ($key === 'replies_until') {
                $query->whereNotNull('parent_message_id');
            }
            $resolved[$key] = $query->findOrFail($input[$key]);
        }
        if ($thread && isset($resolved['thread'])) {
            abort_if($thread->id !== $resolved['thread']->id, 404);
        }
        $origin = $input['origin'] ?? ($thread || $target?->parent_message_id ? 'thread' : 'channel');
        if (! $thread && $origin === 'thread') {
            $thread = $resolved['thread'] ?? null;
            if (! $thread && $target) {
                $thread = $channel->messages()->whereNull('parent_message_id')->findOrFail($target->parent_message_id ?? $target->id);
            }
        }
        $relationThread = $thread ?? ($resolved['thread'] ?? null);
        if ($target?->parent_message_id && $relationThread) {
            abort_if($target->parent_message_id !== $relationThread->id, 404);
        }
        foreach (['edit', 'focus', 'replies_until'] as $key) {
            $message = $resolved[$key] ?? null;
            if ($message && $message->parent_message_id !== null) {
                // 返信境界は、スレッドが入力エラーで未確定なら形式検証後に扱います。
                if ($key !== 'replies_until' || $relationThread) {
                    abort_if($message->parent_message_id !== $relationThread?->id, 404);
                }
            }
        }
        $resolved['thread'] = $thread;

        return $resolved;
    }

    /**
     * 対象IDと表示単位数に共通する正の整数の検証ルールを返す。
     */
    private static function idRules(): array
    {
        return ['bail', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'regex:/^[0-9]+$/D'];
    }

    /**
     * 表示条件を検証し、通常投稿と返信の表示範囲を個別に構築する。
     */
    public static function from(Channel $channel, array $input, ?Message $thread = null, ?Message $target = null, string $prefix = 'messages', ?array $resolved = null): self
    {
        if ($prefix === 'messages') {
            $resolved ??= self::resolveTargets($channel, $input, $thread, $target);
        }
        $rules = ['sometimes', ...self::idRules()];
        Validator::make($input, [
            'messages_until' => $rules, 'messages_pages' => $rules, 'focus' => $rules, 'edit' => $rules,
            'replies_until' => $rules, 'replies_pages' => $rules, 'thread' => $rules,
            'origin' => ['sometimes', 'required', 'in:channel,thread'],
        ], ['*' => '表示条件が正しくありません'])->validate();

        if ($prefix === 'messages') {
            $origin = $input['origin'] ?? ($thread || $target?->parent_message_id ? 'thread' : 'channel');
            if ($origin === 'thread') {
                $thread = $resolved['thread'];
                abort_if($thread === null, 404);
            } else {
                if ($thread || $target?->parent_message_id || isset($input['thread']) || isset($input['replies_until']) || isset($input['replies_pages'])) {
                    throw ValidationException::withMessages(['origin' => '表示条件が正しくありません']);
                }
            }
        }
        $posts = $channel->messages()->where('parent_message_id', $prefix === 'replies' ? $thread->id : null);
        $focus = $resolved['focus'] ?? null;
        $focused = $focus && ($prefix === 'replies' ? $focus->parent_message_id !== null : $focus->parent_message_id === null) ? $focus : null;
        $boundary = $resolved[$prefix.'_until'] ?? $focused ?? (clone $posts)->orderByDesc('created_at')->orderByDesc('id')->first();
        $replies = $prefix === 'messages' && $thread ? self::from($channel, $input, $thread, null, 'replies', $resolved) : null;

        return new self($channel, $boundary, (int) ($input[$prefix.'_pages'] ?? 1), $thread, $replies, $prefix);
    }

    /**
     * 対象が取得済みの通常投稿・返信またはスレッドの親か判定する。
     */
    public function contains(Message $message): bool
    {
        return $this->messages->contains('id', $message->id) || $this->thread?->id === $message->id || ($this->replies?->messages->contains('id', $message->id) ?? false);
    }

    /**
     * 操作元と通常投稿・返信の表示範囲を引き継ぐ値を返す。
     */
    public function context(): array
    {
        return $this->parameters + ($this->thread ? ['origin' => 'thread', 'thread' => $this->thread->id] + ($this->replies?->parameters ?? []) : ['origin' => 'channel']);
    }

    /**
     * 表示範囲と指定したアンカーを引き継ぐ内部URLを生成する。
     */
    public function url(array $extra = [], ?int $anchor = null): string
    {
        return route($this->thread ? 'threads.show' : 'channels.show', ['channel' => $this->channel->id] + ($this->thread ? ['message' => $this->thread->id] : []) + $extra + array_diff_key($this->context(), ['origin' => true, 'thread' => true]))
            .($anchor ? '#message-'.$anchor : '');
    }

    /**
     * 現在の上限を保って表示件数を10件増やすURLを生成する。
     */
    public function olderUrl(): string
    {
        $oldest = $this->messages->first()?->id;
        $url = $this->url([$this->prefix.'_pages' => $this->parameters[$this->prefix.'_pages'] + 1], $oldest);

        return $this->prefix === 'messages' && $oldest === $this->thread?->id
            ? str_replace('#message-'.$oldest, '#thread-left-'.$oldest, $url) : $url;
    }
}
