@php($type = $type ?? 'text')
<div class="sd-field">
  <label for="f-{{ $name }}">{{ $label }}@if (! empty($required)) *@endif</label>
  <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value ?? '') }}"@if (! empty($required)) required @endif @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif>
  @if (! empty($hint))
    <small class="sd-muted">{{ $hint }}</small>
  @endif
</div>
