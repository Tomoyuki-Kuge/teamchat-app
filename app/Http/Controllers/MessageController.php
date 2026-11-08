<?php

namespace App\Http\Controllers;

use App\Actions\Messages\SaveMessage;
use App\Exceptions\MessageConflict;
use App\Http\Requests\MessageRequest;
use App\Models\Channel;
use App\Support\Messages\ConversationPage;
use App\Support\Messages\ConversationRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * メッセージの投稿・編集・削除と削除確認を受け付ける。
 */
class MessageController extends Controller
{
    /**
     * 通常投稿を保存し、チャンネルの最新の会話画面へ移動する。
     */
    public function store(MessageRequest $request, Channel $channel, SaveMessage $action): RedirectResponse
    {
        $action->create($request->user(), $channel, $request->validated('body'));

        return redirect()->route('channels.show', $channel, 303);
    }

    /**
     * 本文を更新し、競合時は確認画面を再表示する。
     */
    public function update(MessageRequest $request, Channel $channel, string $message, SaveMessage $action, ConversationPage $page): Response
    {
        try {
            $action->update($request->user(), $channel, $request->target, $request->validated('body'), (int) $request->validated('expected_version'));
        } catch (MessageConflict $conflict) {
            return $page->render($request, $channel, $conflict->current);
        }

        return redirect()->to($request->range->url([], $request->target->id), 303);
    }

    /**
     * 対象と操作権限を確認し、メッセージの削除確認を表示する。
     */
    public function deleteConfirmation(Request $request, Channel $channel, string $message): Response
    {
        $this->authorize('view', $channel);
        $target = $channel->messages()->with('author')->findOrFail($message);
        $targets = ConversationRange::resolveTargets($channel, $request->query(), null, $target);
        $this->authorize('delete', $target);
        abort_if($target->deleted_at !== null, 409, 'このメッセージは削除されています');
        try {
            $range = ConversationRange::from($channel, $request->query(), null, $target, resolved: $targets);
        } catch (ValidationException $exception) {
            return response()->view('messages.invalid-range', compact('channel'), 422);
        }

        return response()->view('messages.delete-confirmation', compact('channel', 'target', 'range'));
    }

    /**
     * メッセージを削除し、元の会話の表示位置へ戻る。
     */
    public function destroy(MessageRequest $request, Channel $channel, string $message, SaveMessage $action): RedirectResponse
    {
        $action->delete($request->user(), $channel, $request->target);

        return redirect()->to($request->range->url([], $request->target->id), 303);
    }
}
