@extends('layouts.app')
@section('title', 'チャンネルを編集する')
@section('content')
    <div class="page channel-form">
        <h1 class="h-title channel-form__title">チャンネルを編集する</h1>
        @if($invalid)<p class="err" role="alert">表示条件が正しくありません</p>@endif
        <div class="card channel-edit-card"><div class="card__body">
            <form method="POST" action="{{ route('channels.update', $channel) }}" novalidate>
                @csrf
                @method('PATCH')
                @include('channels.fields', $edit)
                <p class="help">{{ $channel->is_private ? 'プライベート' : '公開' }}チャンネルです。公開範囲はあとから変更できません。</p>
                <div class="inline-2 channel-form__actions">
                    <a class="btn" href="{{ route('channels.show', $channel) }}">キャンセル</a>
                    <button class="btn btn--primary" type="submit">保存する</button>
                </div>
            </form>
        </div></div>
        <h2 class="h-heading channel-delete-title">チャンネルを削除する</h2>
        <div class="danger-box">
            <div class="spread">
                <div><div class="t-label">このチャンネルを削除します</div><div class="t-caption">メッセージと返信もすべて削除され、元に戻せません。</div></div>
                @unless($confirmDelete)<a class="btn btn--danger" href="{{ route('channels.edit', ['channel' => $channel, 'confirm_delete' => 1]) }}">削除する</a>@endunless
            </div>
            @if($confirmDelete)
                <div class="card channel-delete-confirmation"><div class="card__body">
                    <h3 class="h-heading">「<span class="channel-text">{{ $channel->name }}</span>」を削除しますか？</h3>
                    <p class="t-body t-muted channel-delete-explanation">メッセージ {{ $postCount }}件と返信 {{ $replyCount }}件も削除されます。この操作は取り消せません。確認のためチャンネル名を入力してください。</p>
                    <form method="POST" action="{{ route('channels.destroy', $channel) }}" novalidate>
                        @csrf
                        @method('DELETE')
                        <div class="field {{ $delete['feedback']->has('confirmation_name') ? 'is-error' : '' }}">
                            <label class="label" for="confirmation_name">確認用チャンネル名<span class="req">*</span></label>
                            <input class="input" id="confirmation_name" name="confirmation_name" value="{{ $delete['values']['confirmation_name'] ?? '' }}" placeholder="{{ $channel->name }}" required aria-describedby="confirmation-feedback" @if($delete['feedback']->has('confirmation_name')) aria-invalid="true" @endif>
                            <div id="confirmation-feedback">@if($delete['feedback']->has('confirmation_name'))<span class="err" role="alert">{{ $delete['feedback']->first('confirmation_name') }}</span>@endif</div>
                        </div>
                        <div class="inline-2 channel-form__actions">
                            <a class="btn" href="{{ route('channels.edit', $channel) }}">やめる</a>
                            <button class="btn btn--danger-solid" type="submit">削除する</button>
                        </div>
                    </form>
                </div></div>
            @endif
        </div>
    </div>
@endsection
