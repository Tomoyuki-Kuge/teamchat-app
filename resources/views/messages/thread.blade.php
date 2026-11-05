<aside class="thread thread-panel" aria-label="スレッド">
    <div class="thread-panel__head"><div><h2>スレッド</h2><div class="t-caption">#{{ $channel->name }}</div></div><a class="icon-btn" href="{{ route('channels.show', ['channel' => $channel] + $range->parameters) }}" aria-label="スレッドを閉じる">×</a></div>
    <div class="thread-panel__messages">
        @include('messages.message', ['message' => $range->thread->load('author')->loadCount('replies'), 'panelParent' => true])
        <div class="thread-count">返信{{ $range->thread->replies_count }}件</div>
        @if($range->replies->hasOlder)
            <div class="message-history"><a href="{{ $range->url(['replies_pages' => $range->replies->parameters['replies_pages'] + 1], $range->replies->messages->first()?->id) }}">以前の返信を表示</a></div>
        @endif
        @forelse($range->replies->messages as $message)
            @include('messages.message', ['panelParent' => false])
        @empty
            <p class="thread-empty">返信がありません</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('replies.store', ['channel' => $channel, 'message' => $range->thread]) }}" class="composer">
        @csrf
        @include('messages.range-fields')
        <div class="composer__box {{ $replyComposer['feedback']->has('body') ? 'is-error' : '' }}">
            <label class="visually-hidden" for="reply-body">返信本文</label>
            <textarea id="reply-body" class="composer__ta" name="body" placeholder="返信を送る" aria-describedby="reply-limit{{ $replyComposer['feedback']->has('body') ? ' reply-error' : '' }}" @if($replyComposer['feedback']->has('body')) aria-invalid="true" @endif>{{ "\n".($replyComposer['values']['body'] ?? '') }}</textarea>
            <div class="composer__foot"><span class="count" id="reply-limit">1,000文字まで</span><button class="btn btn--primary btn--sm" type="submit">返信</button></div>
        </div>
        @if($replyComposer['feedback']->has('body'))<p id="reply-error" class="err" role="alert">{{ $replyComposer['feedback']->first('body') }}</p>@endif
    </form>
</aside>
