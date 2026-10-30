@extends('layouts.auth')
@section('title', '対象が見つかりません')
@section('content')
    <div class="auth__foot"><a href="{{ route('channels.index') }}">チャンネル一覧へ戻る</a></div>
@endsection
