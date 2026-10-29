{{-- ラベル・入力欄・エラー表示をまとめ、パスワード以外の再入力を省きます。 --}}
@props(['name', 'label', 'type' => 'text', 'requiredMark' => true, 'autocomplete' => null, 'help' => null])

<div class="field {{ $errors->has($name) ? 'is-error' : '' }}">
    <label class="label" for="{{ $name }}">{{ $label }}@if($requiredMark)<span class="req">*</span>@endif</label>
    <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" required
        @if($type !== 'password') value="{{ old($name) }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($errors->has($name)) aria-invalid="true" @endif
        @if($help || $errors->has($name)) aria-describedby="{{ $name }}-feedback" @endif>
    <div id="{{ $name }}-feedback">
        @if($help)<span class="help">{{ $help }}</span>@endif
        @error($name)<span class="err" role="alert">{{ $message }}</span>@enderror
    </div>
</div>
