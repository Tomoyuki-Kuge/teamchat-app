<div class="field {{ $feedback->has('name') ? 'is-error' : '' }}">
    <label class="label" for="name">チャンネル名<span class="req">*</span></label>
    <input class="input" id="name" name="name" value="{{ $values['name'] ?? '' }}" required aria-describedby="name-feedback" @if($feedback->has('name')) aria-invalid="true" @endif>
    <div class="field-foot" id="name-feedback">
        <div>@if($feedback->has('name'))<span class="err" role="alert">{{ $feedback->first('name') }}</span>@endif</div>
        <span class="count">50文字まで</span>
    </div>
</div>
<div class="field {{ $feedback->has('description') ? 'is-error' : '' }}">
    <label class="label" for="description">説明</label>
    <textarea class="textarea" id="description" name="description" aria-describedby="description-feedback" @if($feedback->has('description')) aria-invalid="true" @endif>{{ $values['description'] ?? '' }}</textarea>
    <div class="field-foot" id="description-feedback">
        <div>
            @if($feedback->has('description'))<span class="err" role="alert">{{ $feedback->first('description') }}</span>
            @else<span class="help">何について話す場所かを短く書きます。</span>@endif
        </div>
        <span class="count">200文字まで</span>
    </div>
</div>
