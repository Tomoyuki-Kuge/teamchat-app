{{-- ログイン回数の上限を超えた場合に、時間をおいて試すよう案内します。 --}}
@extends('layouts.auth')
@section('title', '時間をおいて再度お試しください')
@section('content')
    <p>ログインの試行回数が多すぎます 時間をおいて再度お試しください</p>
    <div class="auth__foot"><a href="{{ route('login') }}">ログイン画面を開く</a></div>
@endsection
