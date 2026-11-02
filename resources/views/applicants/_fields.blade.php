@php
  use App\Support\Interessenten\Interessenten;
  $box = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
  $val = fn ($k) => old($k, $values[$k] ?? '');
@endphp
<div class="sd-grid">
  @foreach (['adults', 'children'] as $k)
    <div class="sd-field"><label for="d-{{ $k }}">{{ Interessenten::FIELDS[$k][0] }}</label><input id="d-{{ $k }}" name="{{ $k }}" type="number" min="{{ $k === 'adults' ? 1 : 0 }}" max="10" value="{{ $val($k) }}" style="{{ $box }}"></div>
  @endforeach
</div>
<div class="sd-grid">
  @foreach (['occupation', 'employer'] as $k)
    <div class="sd-field"><label for="d-{{ $k }}">{{ Interessenten::FIELDS[$k][0] }}</label><input id="d-{{ $k }}" name="{{ $k }}" maxlength="150" value="{{ $val($k) }}" style="{{ $box }}">@if (Interessenten::FIELDS[$k][2] !== '')<small class="sd-muted">{{ Interessenten::FIELDS[$k][2] }}</small>@endif</div>
  @endforeach
</div>
<div class="sd-grid">
  <div class="sd-field"><label for="d-net_income">{{ Interessenten::FIELDS['net_income'][0] }}</label><input id="d-net_income" name="net_income" inputmode="decimal" value="{{ $val('net_income') !== '' && is_numeric($val('net_income')) ? number_format((float) $val('net_income'), 2, ',', '') : $val('net_income') }}" style="{{ $box }}"><small class="sd-muted">{{ Interessenten::FIELDS['net_income'][2] }}</small></div>
  <div class="sd-field"><label for="d-pets">{{ Interessenten::FIELDS['pets'][0] }}</label><input id="d-pets" name="pets" maxlength="150" value="{{ $val('pets') }}" style="{{ $box }}"><small class="sd-muted">{{ Interessenten::FIELDS['pets'][2] }}</small></div>
</div>
<div class="sd-grid">
  <div class="sd-field"><label for="d-move_in">{{ Interessenten::FIELDS['move_in'][0] }}</label><input id="d-move_in" name="move_in" type="date" value="{{ $val('move_in') }}" style="{{ $box }}"></div>
  <div class="sd-field"><label for="d-credit_ok">{{ Interessenten::FIELDS['credit_ok'][0] }}</label>
    <select id="d-credit_ok" name="credit_ok" style="{{ $box }}">@foreach (Interessenten::CREDIT as $k => $label)<option value="{{ $k }}" @selected($val('credit_ok') === $k || ($val('credit_ok') === '' && $k === 'unbekannt'))>{{ $label }}</option>@endforeach</select></div>
</div>
<div class="sd-field"><label for="d-remark">{{ Interessenten::FIELDS['remark'][0] }}</label><textarea id="d-remark" name="remark" rows="3" maxlength="1500" style="{{ $box }}">{{ $val('remark') }}</textarea><small class="sd-muted">{{ Interessenten::FIELDS['remark'][2] }}</small></div>
