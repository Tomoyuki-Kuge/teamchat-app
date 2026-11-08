<?php

namespace App\Http\Controllers;

use App\Actions\Messages\SaveMessage;
use App\Http\Requests\MessageRequest;
use App\Models\Channel;
use App\Support\Messages\ConversationPage;
use Illuminate\Http\Request;

/**
 * スレッドの表示と返信投稿を扱う。
 */
class ThreadController extends Controller
{
    /**
     * チャンネルの閲覧権限を確認し、スレッド画面を表示する。
     */
    public function show(Request $request, Channel $channel, string $message, ConversationPage $page)
    {
        $this->authorize('view', $channel);

        return $page->render($request, $channel);
    }

    /**
     * 返信を保存し、スレッド画面へ戻す。
     */
    public function store(MessageRequest $request, Channel $channel, string $message, SaveMessage $action)
    {
        $action->create($request->user(), $channel, $request->validated('body'), $request->range->thread);

        return redirect()->route('threads.show', ['channel' => $channel, 'message' => $message] + $request->range->parameters, 303);
    }
}
