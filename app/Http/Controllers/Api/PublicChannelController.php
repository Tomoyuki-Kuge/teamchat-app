<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicApiRequest;
use App\Http\Responses\Api\PublicApiResponse;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

/**
 * 認証状態に依存せず、公開チャンネルと未削除の会話だけを返す。
 */
class PublicChannelController extends Controller
{
    public function index(PublicApiRequest $request): JsonResponse
    {
        $input = $request->validated();
        $page = (int) ($input['page'] ?? 1);
        $query = Channel::query()->active()->where('is_private', false);
        $until = (int) ($input['until_id'] ?? (clone $query)->max('id') ?? 0);
        $query->where('id', '<=', $until);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / 10));
        $channels = $page <= $lastPage
            ? $query->inCreationOrder()->offset(($page - 1) * 10)->limit(10)->get()
            : collect();

        return PublicApiResponse::json([
            'data' => $channels->map(fn (Channel $channel) => [
                'id' => $channel->id, 'name' => $channel->name, 'description' => $channel->description,
            ])->all(),
            'meta' => ['current_page' => $page, 'until_id' => $until, 'per_page' => 10, 'total' => $total, 'last_page' => $lastPage],
            'links' => PublicApiResponse::links('/api/channels', $page, $until, $lastPage),
        ]);
    }

    public function messages(PublicApiRequest $request): JsonResponse
    {
        $channel = $request->route('channel');
        $input = $request->validated();
        $page = (int) ($input['page'] ?? 1);
        $query = $channel->messages()->whereNull('deleted_at');
        $until = (int) ($input['until_id'] ?? (clone $query)->max('id') ?? 0);
        $query->where('id', '<=', $until);
        $messages = (clone $query)->whereNull('parent_message_id');
        $replies = (clone $query)->whereNotNull('parent_message_id');
        $messagesTotal = (clone $messages)->count();
        $repliesTotal = (clone $replies)->count();
        $lastPage = max(1, (int) ceil($messagesTotal / 5), (int) ceil($repliesTotal / 5));
        $rows = function ($rows, int $total) use ($page) {
            if ($page > max(1, (int) ceil($total / 5))) {
                return [];
            }

            return $rows->with('author')->withCount('replies')->orderByDesc('created_at')->orderByDesc('id')
                ->offset(($page - 1) * 5)->limit(5)->get()->map(fn (Message $message) => [
                    'id' => $message->id,
                    'parent_message_id' => $message->parent_message_id,
                    'body' => $message->body,
                    'author_name' => $message->author->name,
                    'created_at' => $message->created_at->copy()->setTimezone('Asia/Tokyo')->format('Y-m-d\TH:i:sP'),
                    'is_edited' => $message->edited_at !== null,
                    'reply_count' => $message->parent_message_id === null ? $message->replies_count : 0,
                ])->all();
        };

        return PublicApiResponse::json([
            'data' => ['messages' => $rows($messages, $messagesTotal), 'replies' => $rows($replies, $repliesTotal)],
            'meta' => ['current_page' => $page, 'until_id' => $until, 'per_type' => 5,
                'messages_total' => $messagesTotal, 'replies_total' => $repliesTotal, 'last_page' => $lastPage],
            'links' => PublicApiResponse::links('/api/channels/'.$channel->id.'/messages', $page, $until, $lastPage),
        ]);
    }
}
