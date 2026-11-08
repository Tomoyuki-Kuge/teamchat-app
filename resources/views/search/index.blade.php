@extends('layouts.app')
@section('title', '検索結果')
@section('content')
<div class="page">
    <h1 class="h-title">メッセージを検索</h1>
    <form class="search-form" method="GET" action="{{ route('search') }}">
        <div class="field {{ $feedback->has('q') ? 'is-error' : '' }}">
            <label class="visually-hidden" for="search-query">検索キーワード</label>
            <div class="inline-2"><input class="input" id="search-query" name="q" value="{{ $q }}" @if($feedback->has('q')) aria-invalid="true" aria-describedby="search-error" @endif><button class="btn btn--primary" type="submit">検索</button></div>
            @if($feedback->has('q'))<p class="err" id="search-error" role="alert">{{ $feedback->first('q') }}</p>@endif
        </div>
    </form>
    @foreach(collect($feedback->messages())->except('q')->flatten() as $error)<p class="err" role="alert">{{ $error }}</p>@endforeach
    @unless($invalid)
        <div class="search-summary">
            <p class="t-caption">「{{ $q }}」の検索結果　{{ $total }}件</p>
            <form class="inline-2" method="GET" action="{{ route('search') }}">
                <input type="hidden" name="q" value="{{ $q }}">
                <label class="t-caption" for="search-sort">投稿日時</label>
                <select class="input" id="search-sort" name="sort"><option value="newest" @selected($sort === 'newest')>新しい順</option><option value="oldest" @selected($sort === 'oldest')>古い順</option></select>
                <button class="btn btn--sm" type="submit">並べ替え</button>
            </form>
        </div>
        @if($total === 0)
            <div class="empty"><h2 class="empty__title">「{{ $q }}」に一致するメッセージはありません</h2><p class="empty__text">別のキーワードで試すか、キーワードを短くしてください。</p></div>
        @else
            <div class="rows">
                @foreach($results as $result)
                    @php
                        $target = ['channel' => $result->channel_id, 'focus' => $result->id];
                        if ($result->parent_message_id) { $target['message'] = $result->parent_message_id; }
                        $url = route($result->parent_message_id ? 'threads.show' : 'channels.show', $target).'#message-'.$result->id;
                    @endphp
                    <a class="row search-result" data-message-id="{{ $result->id }}" href="{{ $url }}"><div class="row__main">
                        <div class="result__meta"><span class="result__ch"><x-channel-symbol :private="$result->channel->is_private" /> {{ $result->channel->name }}</span><span>·</span><span>{{ $result->author->name }}</span><span>·</span><time>{{ $result->created_at_jst->format('Y/m/d H:i') }}</time></div>
                        <div class="t-body msg__body">{!! \App\Support\Search\SearchText::highlight($result->body, $words) !!}</div>
                    </div></a>
                @endforeach
            </div>
            <nav class="search-pagination" aria-label="検索結果のページ">
                @if($page > 1)<a class="btn btn--sm" rel="prev" href="{{ route('search', ['q' => $q, 'sort' => $sort, 'until_id' => $until, 'page' => $page - 1]) }}">前へ</a>@endif
                @foreach(array_unique([1, max(1, $page - 1), $page, min($lastPage, $page + 1), $lastPage]) as $number)
                    <a class="btn btn--sm" @if($number === $page) aria-current="page" @endif href="{{ route('search', ['q' => $q, 'sort' => $sort, 'until_id' => $until, 'page' => $number]) }}">{{ $number }}</a>
                @endforeach
                @if($page < $lastPage)<a class="btn btn--sm" rel="next" href="{{ route('search', ['q' => $q, 'sort' => $sort, 'until_id' => $until, 'page' => $page + 1]) }}">次へ</a>@endif
            </nav>
        @endif
    @endunless
</div>
@endsection
