@foreach($range->context() as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endforeach
