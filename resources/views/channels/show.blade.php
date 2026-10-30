@extends('layouts.app')
@section('title', $channel->name)
@section('mainClass', 'main')
@section('content')
    <div class="ch-head">
        <div class="ch-head__main">
            <h1 class="ch-head__name"><x-channel-symbol :private="$channel->is_private" /><span class="channel-text">{{ $channel->name }}</span></h1>
            <span class="badge {{ $channel->is_private ? 'badge--private' : 'badge--public' }}">{{ $channel->is_private ? 'プライベート' : '公開' }}</span>
            <span class="ch-head__desc">{{ $channel->description }}</span>
        </div>
        @if($channel->creator_id === auth()->id())
            <div class="ch-head__acts">
                <a class="btn btn--sm" href="{{ route('channels.edit', $channel) }}">編集</a>
                <a class="btn btn--sm btn--danger" href="{{ route('channels.edit', ['channel' => $channel, 'confirm_delete' => 1]) }}">削除</a>
            </div>
        @endif
    </div>
    @if($invalid || $rangeError)
        <p class="err message-range-error" role="alert">表示条件が正しくありません</p>
        <a class="message-range-error" href="{{ route('channels.show', $channel) }}">チャンネルを開き直す</a>
    @endif
    <div class="msg-list">
        @if($range->hasOlder)
            <div class="message-history"><a href="{{ $range->olderUrl() }}">以前のメッセージを表示</a></div>
        @endif
        @php($previousDay = null)
        @forelse($range->messages as $message)
            @if($previousDay !== $message->created_at_jst->format('Y-m-d'))
                <div class="day-sep"><span>{{ $message->created_at_jst->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}</span></div>
                @php($previousDay = $message->created_at_jst->format('Y-m-d'))
            @endif
            @include('messages.message')
        @empty
            <div class="empty message-empty"><h2 class="empty__title">メッセージがありません</h2><p class="empty__text">最初のメッセージを送って会話を始めましょう。</p></div>
        @endforelse
    </div>
    <form method="POST" action="{{ route('messages.store', $channel) }}" class="composer">
        @csrf
        @include('messages.range-fields')
        <div class="composer__box {{ $composer['feedback']->has('body') ? 'is-error' : '' }}">
            <label class="visually-hidden" for="message-body">メッセージ本文</label>
            <textarea id="message-body" class="composer__ta" name="body" placeholder="#{{ $channel->name }} にメッセージを送る" aria-describedby="message-limit{{ $composer['feedback']->has('body') ? ' message-error' : '' }}" @if($composer['feedback']->has('body')) aria-invalid="true" @endif>{{ "\n".($composer['values']['body'] ?? '') }}</textarea>
            <div class="composer__foot"><span class="count" id="message-limit">1,000文字まで</span><button class="btn btn--primary btn--sm" type="submit">送信</button></div>
        </div>
        @if($composer['feedback']->has('body'))<p id="message-error" class="err" role="alert">{{ $composer['feedback']->first('body') }}</p>@endif
    </form>
@endsection
