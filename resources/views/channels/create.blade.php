@extends('layouts.app')
@section('title', 'チャンネルを作る')
@section('content')
    <div class="page channel-form">
        <h1 class="h-title channel-form__title">チャンネルを作る</h1>
        <div class="card"><div class="card__body">
            <form method="POST" action="{{ route('channels.store') }}" novalidate>
                @csrf
                @include('channels.fields')
                <fieldset class="field channel-visibility {{ $feedback->has('is_private') ? 'is-error' : '' }}" aria-describedby="visibility-feedback">
                    <legend class="label">公開範囲</legend>
                    <div class="radio-set">
                        <label class="radio">
                            <input type="radio" name="is_private" value="0" @checked(($values['is_private'] ?? null) === '0')>
                            <span><span class="rt">公開</span><span class="rd channel-radio-description">ログインしている社員なら誰でも読み書きできます。</span></span>
                        </label>
                        <label class="radio">
                            <input type="radio" name="is_private" value="1" @checked(($values['is_private'] ?? null) === '1')>
                            <span><span class="rt">プライベート</span><span class="rd channel-radio-description">追加された人だけが読み書きできます。作成者がメンバーを管理します。</span></span>
                        </label>
                    </div>
                    <div id="visibility-feedback">@if($feedback->has('is_private'))<span class="err" role="alert">{{ $feedback->first('is_private') }}</span>@endif</div>
                </fieldset>
                <div class="inline-2 channel-form__actions">
                    <a class="btn" href="{{ route('channels.index') }}">キャンセル</a>
                    <button class="btn btn--primary" type="submit">作成する</button>
                </div>
            </form>
        </div></div>
    </div>
@endsection
