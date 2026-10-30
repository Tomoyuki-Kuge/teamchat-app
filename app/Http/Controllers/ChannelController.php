<?php

namespace App\Http\Controllers;

use App\Actions\Channels\SaveChannel;
use App\Http\Requests\ChannelRequest;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    public function index(Request $request): View
    {
        $channels = Channel::visibleTo($request->user())->with('creator')->inCreationOrder()->get();

        return view('channels.index', compact('channels'));
    }

    public function create(Request $request): View
    {
        return view('channels.create', $this->formState($request, 'channels.store', ['is_private' => '0']));
    }

    public function store(ChannelRequest $request, SaveChannel $action): RedirectResponse
    {
        $channel = $action->create($request->user(), $request->validated());

        return redirect()->route('channels.show', $channel, 303);
    }

    public function show(Channel $channel): View
    {
        $this->authorize('view', $channel);

        return view('channels.show', compact('channel'));
    }

    public function edit(Request $request, Channel $channel): Response
    {
        $this->authorize('update', $channel);
        $invalid = $request->query->has('confirm_delete') && $request->query('confirm_delete') !== '1';
        $confirmDelete = $request->query('confirm_delete') === '1';
        $postCount = $confirmDelete ? $channel->messages()->whereNull('parent_message_id')->count() : 0;
        $replyCount = $confirmDelete ? $channel->messages()->whereNotNull('parent_message_id')->count() : 0;
        $edit = $this->formState($request, 'channels.update:'.$channel->id, $channel->only('name', 'description'));
        $delete = $this->formState($request, 'channels.destroy:'.$channel->id);

        return response()->view('channels.edit', compact('channel', 'confirmDelete', 'postCount', 'replyCount', 'edit', 'delete', 'invalid'), $invalid ? 422 : 200);
    }

    public function update(ChannelRequest $request, Channel $channel, SaveChannel $action): RedirectResponse
    {
        $action->update($request->user(), $channel, $request->validated());

        return redirect()->route('channels.show', $channel, 303);
    }

    public function destroy(ChannelRequest $request, Channel $channel, SaveChannel $action): RedirectResponse
    {
        $action->delete($request->user(), $channel, $request->validated('confirmation_name'));

        return redirect()->route('channels.index', [], 303);
    }

    private function formState(Request $request, string $key, array $defaults = []): array
    {
        $matches = $request->session()->get('channel_form') === $key;

        return [
            'values' => $matches ? array_replace($defaults, $request->session()->getOldInput()) : $defaults,
            'feedback' => $matches ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag,
        ];
    }
}
