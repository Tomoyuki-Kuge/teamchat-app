@extends('layouts.auth')
@section('title', $exception->getMessage() === '作成者は外せません' ? '作成者は外せません' : 'この操作はできません')
@section('content')
    <div class="auth__foot"><a href="{{ route('channels.index') }}">チャンネル一覧へ戻る</a></div>
@endsection
