@extends('layouts.app')
@section('title', '表示条件が正しくありません')
@section('content')
    <div class="page"><p class="err" role="alert">表示条件が正しくありません</p><a href="{{ route('channels.show', $channel) }}">チャンネルを開き直す</a></div>
@endsection
