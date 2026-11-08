{{-- ログイン後の画面で共通の上部バーと本文の表示場所を用意します。 --}}
@extends('layouts.base')

@section('body')
    <div class="app">
        <header class="topbar">
            <div class="topbar__brand"><span class="brandmark">チ</span>チームチャットアプリ</div>
            <form class="topbar__search" method="GET" action="{{ route('search') }}">
                <label class="visually-hidden" for="global-search">メッセージを検索</label>
                <div class="inline-2"><input class="input" id="global-search" name="q" placeholder="メッセージを検索"><button class="btn btn--sm" type="submit">検索</button></div>
            </form>
            <div class="topbar__me">
                <span class="nm">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    {{-- 別サイトからの不正なフォーム送信を防ぐためのトークンです。 --}}
                    @csrf
                    <button class="logout" type="submit">ログアウト</button>
                </form>
            </div>
        </header>
        <div class="app__body">
            @include('channels.sidebar')
            <main class="@yield('mainClass', 'main main--scroll')">
                @yield('content')
            </main>
        </div>
    </div>
@endsection
