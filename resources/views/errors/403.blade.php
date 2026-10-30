@extends('layouts.auth')
@section('title', 'この操作はできません')
@section('content')
    <div class="auth__foot"><a href="{{ route('channels.index') }}">チャンネル一覧へ戻る</a></div>
@endsection
