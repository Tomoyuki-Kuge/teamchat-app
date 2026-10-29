{{-- メールとパスワードをFortifyのログイン処理へ送信します。 --}}
@extends('layouts.auth')

@section('title', 'ログイン')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-auth-field name="email" label="メールアドレス" autocomplete="username" help="255文字まで" :required-mark="false" />
        <x-auth-field name="password" label="パスワード" type="password" autocomplete="current-password" :required-mark="false" />
        <button class="btn btn--primary btn--lg btn--block" type="submit">ログイン</button>
    </form>
    <div class="auth__foot">アカウントをお持ちでない方は <a href="{{ route('register') }}">新規登録</a></div>
@endsection
