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

class MessageController extends Controller
{
    public function store(MessageRequest $request, Channel $channel, SaveMessage $action): RedirectResponse
    {
        $action->create($request->user(), $channel, $request->validated('body'));

        return redirect()->route('channels.show', $channel, 303);
    }

    public function update(MessageRequest $request, Channel $channel, string $message, SaveMessage $action, ConversationPage $page): Response
    {
        try {
            $action->update($request->user(), $channel, $request->target, $request->validated('body'), (int) $request->validated('expected_version'));
        } catch (MessageConflict $conflict) {
            return $page->render($request, $channel, $conflict->current);
        }

        return redirect()->to($request->range->url([], $request->target->id), 303);
    }

    public function deleteConfirmation(Request $request, Channel $channel, string $message): Response
    {
        $this->authorize('view', $channel);
        $target = $channel->messages()->whereNull('parent_message_id')->with('author')->findOrFail($message);
        $this->authorize('delete', $target);
        abort_if($target->deleted_at !== null, 409, 'このメッセージは削除されています');
        try {
            $range = ConversationRange::from($channel, $request->query());
        } catch (ValidationException $exception) {
            return response()->view('messages.invalid-range', compact('channel'), 422);
        }

        return response()->view('messages.delete-confirmation', compact('channel', 'target', 'range'));
    }

    public function destroy(MessageRequest $request, Channel $channel, string $message, SaveMessage $action): RedirectResponse
    {
        $action->delete($request->user(), $channel, $request->target);

        return redirect()->to($request->range->url([], $request->target->id), 303);
    }
}
