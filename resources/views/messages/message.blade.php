<article class="msg {{ $message->deleted_at ? 'msg--deleted' : '' }}" id="message-{{ $message->id }}" tabindex="0">
    <span class="avatar avatar--{{ ['a', 'b', 'c', 'd'][($message->user_id - 1) % 4] }}" aria-hidden="true">{{ mb_substr($message->author->name, 0, 2) }}</span>
    <div class="message-content">
        <div class="msg__head"><span class="msg__name">{{ $message->author->name }}</span><time class="msg__time" datetime="{{ $message->created_at_jst->toIso8601String() }}">{{ $message->created_at_jst->format('Y/m/d H:i') }}</time></div>
        @if($message->deleted_at)
            <div class="msg__deleted-note">このメッセージは削除されました</div>
        @elseif($editMessage?->id === $message->id)
            @include('messages.editor')
        @else
            <div class="msg__body">{{ $message->body }}@if($message->edited_at)<span class="msg__edited"> 編集済み</span>@endif</div>
        @endif
        @if($message->replies_count > 0)
            <span class="thread-link is-disabled" aria-disabled="true">返信{{ $message->replies_count }}件</span>
        @endif
    </div>
    @if($editMessage?->id !== $message->id)
        <div class="msg__acts">
            <button class="icon-btn" type="button" title="返信" aria-label="返信" disabled><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2.5 3.5h11v7h-6l-3 2.5v-2.5h-2z"/></svg></button>
            @if(! $message->deleted_at && $message->user_id === auth()->id())
                <a class="icon-btn" href="{{ $range->url(['edit' => $message->id], $message->id) }}" title="編集" aria-label="メッセージを編集"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11 2.6 13.4 5 5.4 13H3v-2.4z"/></svg></a>
                <a class="icon-btn" href="{{ route('messages.delete-confirmation', ['channel' => $channel, 'message' => $message, 'origin' => 'channel'] + $range->parameters) }}" title="削除" aria-label="メッセージを削除"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5"/></svg></a>
            @endif
        </div>
    @endif
</article>
