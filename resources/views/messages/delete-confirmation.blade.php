@extends('layouts.app')
@section('title', 'メッセージ削除確認')
@section('mainClass', 'main main--scroll')
@section('content')
    <div class="page">
        <h1 class="h-title channel-form__title">メッセージ削除確認</h1>
        <div class="danger-box">
            <h2 class="h-heading">このメッセージを削除しますか？</h2>
            <p class="channel-delete-explanation">この操作は取り消せません。削除後は元の位置に「このメッセージは削除されました」と表示されます。</p>
            <div class="message-preview">
                <div class="msg__head"><span class="msg__name">{{ $target->author->name }}</span><span class="msg__time">{{ $target->created_at_jst->format('Y/m/d H:i') }}</span></div>
                <div class="msg__body">{{ $target->body }}</div>
            </div>
            <form method="POST" action="{{ route('messages.destroy', ['channel' => $channel, 'message' => $target]) }}" class="inline-2">
                @csrf
                @method('DELETE')
                @include('messages.range-fields')
                <button class="btn btn--danger-solid" type="submit">削除する</button>
                <a class="btn" href="{{ $range->url([], $target->id) }}">やめる</a>
            </form>
        </div>
    </div>
@endsection
