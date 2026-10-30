@props(['private' => false])
<span class="sig" aria-label="{{ $private ? 'プライベート' : '公開' }}">
    @if($private)
        <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3.25" y="7" width="9.5" height="6.5" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>
    @else
        #
    @endif
</span>
