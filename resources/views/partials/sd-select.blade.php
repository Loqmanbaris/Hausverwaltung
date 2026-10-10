<div class="sd-field">
  <label for="f-{{ $name }}">{{ $label }}@if (! empty($required)) *@endif</label>
  <select id="f-{{ $name }}" name="{{ $name }}"@if (! empty($required)) required @endif>
    @if (empty($noBlank))
      <option value="">– bitte wählen –</option>
    @endif
    @foreach ($options as $key => $text)
      <option value="{{ $key }}" @selected((string) old($name, $value ?? '') === (string) $key)>{{ $text }}</option>
    @endforeach
  </select>
  @if (! empty($hint))
    <small class="sd-muted">{{ $hint }}</small>
  @endif
</div>
