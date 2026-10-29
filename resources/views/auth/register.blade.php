{{-- 登録内容をFortifyへ送信し、入力欄ごとにエラーを表示します。 --}}
@extends('layouts.auth')

@section('title', '新規登録')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <x-auth-field name="name" label="表示名" autocomplete="name" help="メッセージに表示される名前です。30文字まで" />
        <x-auth-field name="email" label="メールアドレス" autocomplete="username" help="255文字まで" />
        <x-auth-field name="password" label="パスワード" type="password" autocomplete="new-password" help="8〜72文字で入力してください" />
        <x-auth-field name="password_confirmation" label="パスワード（確認用）" type="password" autocomplete="new-password" />
        <button class="btn btn--primary btn--lg btn--block" type="submit">登録する</button>
    </form>
    <div class="auth__foot">すでにアカウントをお持ちの方は <a href="{{ route('login') }}">ログイン</a></div>
@endsection
