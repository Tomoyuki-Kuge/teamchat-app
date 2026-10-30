<input type="hidden" name="origin" value="channel">
@foreach($range->parameters as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endforeach
