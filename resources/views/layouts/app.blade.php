{{-- ログイン後の画面で共通の上部バーと本文の表示場所を用意します。 --}}
@extends('layouts.base')

@section('body')
    <div class="app">
        <header class="topbar">
            <div class="topbar__brand"><span class="brandmark">チ</span>チームチャットアプリ</div>
            <div aria-hidden="true"></div>
            <div class="topbar__me">
                <span class="nm">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    {{-- 別サイトからの不正なフォーム送信を防ぐためのトークンです。 --}}
                    @csrf
                    <button class="logout" type="submit">ログアウト</button>
                </form>
            </div>
        </header>
        <main class="main main--scroll">
            @yield('content')
        </main>
    </div>
@endsection
