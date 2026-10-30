@extends('layouts.app')
@section('title', 'チャンネル一覧')
@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="h-title">チャンネル一覧</h1>
                <p class="t-caption channel-intro">公開チャンネルは、ログインしている社員なら誰でも読み書きできます。</p>
            </div>
            <a class="btn btn--primary" href="{{ route('channels.create') }}">チャンネルを作る</a>
        </div>
        @if($channels->isEmpty())
            <div class="empty">
                <h2 class="h-heading">チャンネルがありません</h2>
                <p class="t-label t-muted">「チャンネルを作る」から会話する場所を作成できます。</p>
            </div>
        @else
            <div class="rows">
                @foreach($channels as $item)
                    <div class="row">
                        <div class="row__main">
                            <div class="row__title">
                                <x-channel-symbol :private="$item->is_private" />
                                <span class="channel-text">{{ $item->name }}</span>
                                <span class="badge {{ $item->is_private ? 'badge--private' : 'badge--public' }}">{{ $item->is_private ? 'プライベート' : '公開' }}</span>
                            </div>
                            <div class="row__sub">@if($item->description !== null){{ $item->description }} · @endif作成者：{{ $item->creator->name }}</div>
                        </div>
                        <div class="row__acts">
                            @if($item->creator_id === auth()->id())
                                @if($item->is_private)<button class="btn btn--sm" type="button" disabled>メンバー</button>@endif
                                <a class="btn btn--sm" href="{{ route('channels.edit', $item) }}">編集</a>
                            @endif
                            <a class="btn btn--sm" href="{{ route('channels.show', $item) }}">開く</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
