<form method="POST" action="{{ route('messages.update', ['channel' => $channel, 'message' => $message]) }}" class="message-editor">
    @csrf
    @method('PATCH')
    @include('messages.range-fields')
    <input type="hidden" name="expected_version" value="{{ $editor['values']['expected_version'] }}">
    @if($conflict)
        <div class="message-conflict" role="alert">
            <p>別の画面で編集されています 内容を確認して保存してください</p>
            <p class="label">現在保存されている本文</p>
            <div class="msg__body">{{ $conflict->body }}</div>
        </div>
    @endif
    <div class="field {{ $editor['feedback']->has('body') ? 'is-error' : '' }}">
        <label class="label" for="edit-body">{{ $conflict ? '自分の入力' : 'メッセージを編集' }}</label>
        <textarea id="edit-body" class="textarea" name="body" rows="3" aria-describedby="edit-limit{{ $editor['feedback']->has('body') ? ' edit-error' : '' }}" @if($editor['feedback']->has('body')) aria-invalid="true" @endif>{{ "\n".($editor['values']['body'] ?? '') }}</textarea>
        <span id="edit-limit" class="count">1,000文字まで</span>
        @if($editor['feedback']->has('body'))<p id="edit-error" class="err" role="alert">{{ $editor['feedback']->first('body') }}</p>@endif
        @if($editor['feedback']->has('expected_version'))<p class="err" role="alert">{{ $editor['feedback']->first('expected_version') }}</p>@endif
    </div>
    <div class="inline-2">
        <button class="btn btn--primary btn--sm" type="submit">{{ $conflict ? '確認した内容で保存' : '保存' }}</button>
        <a class="btn btn--sm" href="{{ $range->url([], $message->id) }}">やめる</a>
    </div>
</form>
