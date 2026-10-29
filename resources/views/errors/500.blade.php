{{-- サーバー内部の情報を表示せず、失敗と結果確認の案内を表示します。 --}}
@extends('layouts.auth')
@section('title', '処理に失敗しました')
@section('content')
    <p>処理に失敗したため時間をおいて再度お試しください</p>
    <p>操作が完了している場合があります。画面を開き直して結果を確認してください。</p>
    <div class="auth__foot"><a href="{{ route('login') }}">ログイン画面を開く</a></div>
@endsection
