<?php

namespace App\Http\Controllers;

use App\Actions\Channels\SaveChannel;
use App\Http\Requests\ChannelRequest;
use App\Models\Channel;
use App\Support\Messages\ConversationPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * チャンネルの一覧・表示と作成・編集・削除を受け付ける。
 */
class ChannelController extends Controller
{
    /**
     * 閲覧できるチャンネルの一覧を表示する。
     */
    public function index(Request $request): View
    {
        $channels = Channel::visibleTo($request->user())->with('creator')->inCreationOrder()->get();

        return view('channels.index', compact('channels'));
    }

    /**
     * チャンネルの作成フォームを表示する。
     */
    public function create(Request $request): View
    {
        return view('channels.create', $this->formState($request, 'channels.store', ['is_private' => '0']));
    }

    /**
     * チャンネルを作成し、その会話画面へ移動する。
     */
    public function store(ChannelRequest $request, SaveChannel $action): RedirectResponse
    {
        $channel = $action->create($request->user(), $request->validated());

        return redirect()->route('channels.show', $channel, 303);
    }

    /**
     * チャンネルの会話画面を表示する。
     */
    public function show(Request $request, Channel $channel, ConversationPage $page): Response
    {
        return $page->render($request, $channel);
    }

    /**
     * チャンネルの編集フォームと指定時の削除確認を表示する。
     */
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

    /**
     * チャンネル情報を更新し、その会話画面へ移動する。
     */
    public function update(ChannelRequest $request, Channel $channel, SaveChannel $action): RedirectResponse
    {
        $action->update($request->user(), $channel, $request->validated());

        return redirect()->route('channels.show', $channel, 303);
    }

    /**
     * チャンネルを削除し、一覧画面へ移動する。
     */
    public function destroy(ChannelRequest $request, Channel $channel, SaveChannel $action): RedirectResponse
    {
        $action->delete($request->user(), $channel, $request->validated('confirmation_name'));

        return redirect()->route('channels.index', [], 303);
    }

    /**
     * 対象フォームの入力値とエラーを復元する。
     */
    private function formState(Request $request, string $key, array $defaults = []): array
    {
        $matches = $request->session()->get('channel_form') === $key;

        return [
            'values' => $matches ? array_replace($defaults, $request->session()->getOldInput()) : $defaults,
            'feedback' => $matches ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag,
        ];
    }
}
