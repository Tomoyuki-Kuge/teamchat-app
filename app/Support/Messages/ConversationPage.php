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

class ConversationPage
{
    public function render(Request $request, Channel $channel, ?Message $conflict = null): Response
    {
        if ($conflict) {
            // 例外を受け取るまでに削除・所属解除・再編集が起こり得るため、IDだけを引き継ぎます。
            return DB::transaction(function () use ($request, $channel, $conflict) {
                $currentChannel = Channel::whereKey($channel->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('view', $currentChannel);
                $current = $currentChannel->messages()->whereNull('parent_message_id')
                    ->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
                $current->setRelation('channel', $currentChannel);
                Gate::authorize('update', $current);
                abort_if($current->deleted_at !== null, 409, 'このメッセージは削除されています');

                // HTMLの生成もロック内で終え、削除と競合表示を直列化します。
                return $this->renderPage($request, $currentChannel, $current);
            });
        }

        return $this->renderPage($request, $channel);
    }

    private function renderPage(Request $request, Channel $channel, ?Message $conflict = null): Response
    {
        Gate::authorize('view', $channel);
        $editMessage = null;
        $invalid = false;
        try {
            $range = ConversationRange::from($channel, $request->isMethod('GET') ? $request->query() : $request->only('messages_until', 'messages_pages', 'origin'));
            if ($conflict || $request->query->has('edit')) {
                $editMessage = $conflict ?? $channel->messages()->whereNull('parent_message_id')->findOrFail($request->query('edit'));
                Gate::authorize('update', $editMessage);
                abort_if($editMessage->deleted_at !== null, 409, 'このメッセージは削除されています');
                if (! $range->messages->contains('id', $editMessage->id)) {
                    throw ValidationException::withMessages(['edit' => '表示条件が正しくありません']);
                }
            }
        } catch (ValidationException $exception) {
            $invalid = true;
            $range = ConversationRange::from($channel, []);
            $editMessage = null;
        }
        $form = $request->session()->get('message_form', '');
        $feedback = $request->session()->get('errors')?->getBag('default') ?? new MessageBag;
        $rangeError = (str_starts_with($form, 'messages.store:'.$channel->id.':') || str_starts_with($form, 'messages.update:'.$channel->id.':') || str_starts_with($form, 'messages.destroy:'.$channel->id.':'))
            && $feedback->hasAny(['origin', 'messages_until', 'messages_pages', 'edit']);
        $composer = $this->formState($request, 'messages.store:'.$channel->id.':new', ['body' => '']);
        $editor = $editMessage ? $this->formState($request, 'messages.update:'.$channel->id.':'.$editMessage->id,
            ['body' => $editMessage->body, 'expected_version' => $editMessage->version]) : null;
        if ($conflict) {
            $editor = ['values' => ['body' => $request->input('body'), 'expected_version' => $conflict->version], 'feedback' => new MessageBag];
        }

        return response()->view('channels.show', compact('channel', 'range', 'editMessage', 'composer', 'editor', 'conflict', 'invalid', 'rangeError'), $invalid ? 422 : ($conflict ? 409 : 200));
    }

    private function formState(Request $request, string $key, array $defaults): array
    {
        $matches = $request->session()->get('message_form') === $key;

        return [
            'values' => $matches ? array_replace($defaults, $request->session()->getOldInput()) : $defaults,
            'feedback' => $matches ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag,
        ];
    }
}
