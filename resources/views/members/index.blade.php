@extends('layouts.app')
@section('title', 'メンバー管理')
@section('content')
<div class="page">
    <div class="page__head">
        <div><h1 class="h-title row__title"><x-channel-symbol :private="true" /><span class="channel-text">{{ $channel->name }}</span><span class="badge badge--private">プライベート</span></h1><p class="t-caption channel-intro">{{ $channel->description }} · 追加された人だけが読み書きできます。</p></div>
        <a class="btn btn--sm" href="{{ route('channels.show', $channel) }}">チャンネルを開く</a>
    </div>
    <div class="card member-add"><div class="card__body">
        <h2 class="h-heading member-heading">メンバーを追加する</h2>
        <form method="GET" action="{{ route('members.index', $channel) }}">
            <div class="field {{ $feedback->has('q') ? 'is-error' : '' }}">
                <label class="label" for="member-query">表示名またはメールアドレスで探す</label>
                <div class="inline-2"><input class="input" id="member-query" name="q" value="{{ $q }}" @if($feedback->has('q')) aria-invalid="true" aria-describedby="query-error" @endif><button class="btn" type="submit">検索</button></div>
                @if($feedback->has('q'))<p class="err" id="query-error" role="alert">{{ $feedback->first('q') }}</p>@endif
            </div>
        </form>
        <form method="POST" action="{{ route('members.store', $channel) }}" novalidate>
            @csrf
            <input type="hidden" name="q" value="{{ $q }}">
            <fieldset class="member-candidates {{ $feedback->has('user_id') ? 'is-error' : '' }}" @if($feedback->has('user_id')) aria-describedby="member-error" @endif>
                <legend class="label">追加候補</legend>
                @foreach($candidates as $candidate)
                    <label class="row"><input type="radio" name="user_id" value="{{ $candidate->id }}" @checked((string) $selected === (string) $candidate->id)><span class="row__main"><span class="row__title">{{ $candidate->name }}</span><span class="row__sub">{{ $candidate->email }}</span></span></label>
                @endforeach
                @if($candidates->isEmpty())
                    <div class="empty"><h3 class="h-heading">{{ $searched ? '追加できるメンバーが見つかりません' : 'メンバーを検索してください' }}</h3><p class="t-caption">{{ $searched ? '検索語を変えて探してください' : '表示名またはメールアドレスで探せます' }}</p></div>
                @endif
            </fieldset>
            @if($feedback->has('user_id'))<p class="err" id="member-error" role="alert">{{ $feedback->first('user_id') }}</p>@endif
            <button class="btn btn--primary" type="submit">追加</button>
        </form>
    </div></div>
    <h2 class="h-heading member-heading">メンバー　{{ $members->count() }}人</h2>
    @foreach($removeFeedback->all() as $error)<p class="err" role="alert">{{ $error }}</p>@endforeach
    <div class="rows">
        @foreach($members as $member)
            <div class="row"><span class="avatar">{{ mb_substr($member->name, 0, 2) }}</span><div class="row__main"><div class="row__title">{{ $member->name }} @if($member->id === $channel->creator_id)<span class="badge badge--owner">作成者</span>@endif</div><div class="row__sub">{{ $member->email }}</div></div>
                <div class="row__acts">
                    @if($member->id === $channel->creator_id)<span class="t-caption">作成者は外せません</span><button class="btn btn--sm" type="button" disabled>外す</button>
                    @else
                        <form method="POST" action="{{ route('members.destroy', ['channel' => $channel, 'user' => $member->id]) }}">@csrf @method('DELETE')<input type="hidden" name="q" value="{{ $q }}"><button class="btn btn--sm btn--danger" type="submit">外す</button></form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
