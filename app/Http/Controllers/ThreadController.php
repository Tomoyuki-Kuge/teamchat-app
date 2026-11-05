<?php

namespace App\Http\Controllers;

use App\Actions\Messages\SaveMessage;
use App\Http\Requests\MessageRequest;
use App\Models\Channel;
use App\Support\Messages\ConversationPage;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function show(Request $request, Channel $channel, string $message, ConversationPage $page)
    {
        $this->authorize('view', $channel);

        return $page->render($request, $channel);
    }

    public function store(MessageRequest $request, Channel $channel, string $message, SaveMessage $action)
    {
        $action->create($request->user(), $channel, $request->validated('body'), $request->range->thread);

        return redirect()->route('threads.show', ['channel' => $channel, 'message' => $message] + $request->range->parameters, 303);
    }
}
