<?php

namespace App\Support\Messages;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 会話・スレッド画面の表示と入力エラー・編集競合の再表示を扱う。
 */
class ConversationPage
{
    /**
     * 会話画面を表示し、編集競合時は現在の権限と状態をロック下で再確認する。
     */
    public function render(Request $request, Channel $channel, ?Message $conflict = null): Response
    {
        if ($conflict) {
            // 例外を受け取るまでに削除・所属解除・再編集が起こり得るため、IDだけを引き継ぎます。
            return DB::transaction(function () use ($request, $channel, $conflict) {
                $currentChannel = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('view', $currentChannel);
                $current = $currentChannel->messages()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
                $current->setRelation('channel', $currentChannel);
                Gate::authorize('update', $current);
                abort_if($current->deleted_at !== null, 409, 'このメッセージは削除されています');

                // HTMLの生成もロック内で終え、削除と競合表示を直列化します。
                return $this->renderPage($request, $currentChannel, $current);
            });
        }

        return $this->renderPage($request, $channel);
    }

    /**
     * 対象と権限を確認し、表示範囲・フォーム状態を含む会話画面を返す。
     */
    private function renderPage(Request $request, Channel $channel, ?Message $conflict = null): Response
    {
        Gate::authorize('view', $channel);
        $invalid = false;
        $thread = $request->routeIs('threads.show', 'replies.store') ? $channel->messages()->whereNull('parent_message_id')->findOrFail($request->route('message')) : null;
        $input = $request->isMethod('GET') ? $request->query() : $request->only('messages_until', 'messages_pages', 'replies_until', 'replies_pages', 'origin', 'thread');
        $targets = ConversationRange::resolveTargets($channel, $input, $thread, $conflict);
        $editMessage = $conflict ?? ($targets['edit'] ?? null);
        if ($editMessage) {
            Gate::authorize('update', $editMessage);
            abort_if($editMessage->deleted_at !== null, 409, 'このメッセージは削除されています');
        }
        try {
            $range = ConversationRange::from($channel, $input, $thread, $conflict, resolved: $targets);
            if ($editMessage && ! $range->contains($editMessage)) {
                throw ValidationException::withMessages(['edit' => '表示条件が正しくありません']);
            }
        } catch (ValidationException $exception) {
            $invalid = true;
            $range = ConversationRange::from($channel, [], $thread);
            $editMessage = null;
        }
        $form = $request->session()->get('message_form', '');
        $feedback = $request->session()->get('errors')?->getBag('default') ?? new MessageBag;
        $rangeError = (str_starts_with($form, 'replies.store:'.$channel->id.':') || str_starts_with($form, 'messages.store:'.$channel->id.':') || str_starts_with($form, 'messages.update:'.$channel->id.':') || str_starts_with($form, 'messages.destroy:'.$channel->id.':'))
            && $feedback->hasAny(['origin', 'thread', 'messages_until', 'messages_pages', 'replies_until', 'replies_pages', 'edit']);
        $composer = $this->formState($request, 'messages.store:'.$channel->id.':new', ['body' => '']);
        $replyComposer = $range->thread ? $this->formState($request, 'replies.store:'.$channel->id.':'.$range->thread->id, ['body' => '']) : null;
        $editor = $editMessage ? $this->formState($request, 'messages.update:'.$channel->id.':'.$editMessage->id,
            ['body' => $editMessage->body, 'expected_version' => $editMessage->version]) : null;
        if ($conflict) {
            $editor = ['values' => ['body' => $request->input('body'), 'expected_version' => $conflict->version], 'feedback' => new MessageBag];
        }

        return response()->view('channels.show', compact('channel', 'range', 'editMessage', 'composer', 'editor', 'conflict', 'invalid', 'rangeError', 'replyComposer'), $invalid ? 422 : ($conflict ? 409 : 200));
    }

    /**
     * 対象フォームの入力値とエラーを復元する。
     */
    private function formState(Request $request, string $key, array $defaults): array
    {
        $matches = $request->session()->get('message_form') === $key;

        return [
            'values' => $matches ? array_replace($defaults, $request->session()->getOldInput()) : $defaults,
            'feedback' => $matches ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag,
        ];
    }
}
