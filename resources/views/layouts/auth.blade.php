{{-- ログイン・登録・エラー画面で共通のカード枠を用意します。 --}}
@extends('layouts.base')

@section('body')
    <main class="auth">
        <div class="auth__card">
            <div class="auth__brand"><span class="brandmark">チ</span>チームチャットアプリ</div>
            <h1 class="auth__title">@yield('title')</h1>
            @yield('content')
        </div>
    </main>
@endsection
