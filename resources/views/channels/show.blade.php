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
@endsection
