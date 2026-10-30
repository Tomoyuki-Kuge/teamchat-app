<nav class="sidebar" aria-label="チャンネル">
    <div class="side-group">チャンネル</div>
    @foreach($sidebarChannels as $sidebarChannel)
        <a class="side-item {{ isset($channel) && $channel->id === $sidebarChannel->id ? 'is-selected' : '' }}"
            href="{{ route('channels.show', $sidebarChannel) }}"
            @if(isset($channel) && $channel->id === $sidebarChannel->id) aria-current="page" @endif>
            <x-channel-symbol :private="$sidebarChannel->is_private" />
            <span class="nm channel-text">{{ $sidebarChannel->name }}</span>
        </a>
    @endforeach
    <div class="side-foot"><a href="{{ route('channels.index') }}">チャンネル一覧を見る</a></div>
</nav>
