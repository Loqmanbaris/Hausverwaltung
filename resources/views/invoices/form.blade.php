@extends('layouts.app')
@section('title', $inv ? 'Entwurf bearbeiten' : 'Neue Rechnung')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Rechnung\Berechnung;
  $box = 'padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit;width:100%;box-sizing:border-box';
  $buyer = (array) ($inv?->buyer ?? []);
  $kind = old('recipient_kind', $inv?->recipient_kind ?? 'tenant');
  $b = fn (string $k) => old('b_'.$k, $buyer[$k] ?? '');
  $d = fn (string $k) => old($k, $inv?->{$k}?->format('Y-m-d') ?? '');
  $rows = [];
  if (old('lines') !== null) {
      foreach ((array) old('lines') as $r) { $rows[] = ['name' => $r['name'] ?? '', 'qty' => $r['qty'] ?? '', 'unit' => $r['unit'] ?? 'C62', 'price' => $r['price'] ?? '', 'rate' => $r['rate'] ?? 19, 'exempt' => $r['exempt'] ?? '']; }
  } else {
      foreach ($lines as $l) { $rows[] = ['name' => $l->name, 'qty' => rtrim(rtrim(number_format((float) $l->quantity, 3, ',', ''), '0'), ','), 'unit' => $l->unit, 'price' => number_format((float) $l->unit_net, 2, ',', ''), 'rate' => (int) $l->vat_rate, 'exempt' => $l->exemption]; }
  }
  while (count($rows) < 4) { $rows[] = ['name' => '', 'qty' => '', 'unit' => 'C62', 'price' => '', 'rate' => 19, 'exempt' => '']; }
  $klein = $mode === 'klein';
@endphp
<style>
  .iv-row{display:grid;grid-template-columns:2fr .7fr .9fr 1fr .8fr;gap:.5rem;align-items:end;padding:.7rem 0;border-top:1px solid #e5e8ec}
  .iv-row .full{grid-column:1 / -1}
  @@media (max-width:700px){.iv-row{grid-template-columns:1fr 1fr}.iv-row .name{grid-column:1 / -1}}
  .iv-two{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
  .iv-grid{display:grid;gap:.8rem}
</style>
<div class="sd-head"><h1>{{ $inv ? 'Entwurf bearbeiten' : 'Neue Rechnung' }}</h1></div>
@if ($klein)<div class="alert warn">Die Rechnungsdaten stehen auf <strong>Kleinunternehmer (§ 19 UStG)</strong>. Alle Positionen werden ohne Umsatzsteuer geschrieben.</div>@endif
@if ($errors->any())<div class="alert error"><ul style="margin:0 0 0 1.1rem">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $inv ? route('invoices.update', $inv) : route('invoices.store') }}" class="iv-grid">
  @csrf
  @if ($inv) @method('PUT') @endif

  <div class="sd-card iv-grid">
    <h2 style="margin:0">Empfänger</h2>
    <div>
      <label><input type="radio" name="recipient_kind" value="tenant" @checked($kind === 'tenant')> Mieter</label>
      <label style="margin-left:1rem"><input type="radio" name="recipient_kind" value="owner" @checked($kind === 'owner')> Eigentümer</label>
      <label style="margin-left:1rem"><input type="radio" name="recipient_kind" value="free" @checked($kind === 'free')> Sonstiger Kunde</label>
    </div>
    <div data-kind="tenant">
      <label for="tenant_id">Mieter</label>
      <select id="tenant_id" name="tenant_id" style="{{ $box }}">
        <option value="">Bitte wählen</option>
        @foreach ($tenants as $t)<option value="{{ $t->id }}" data-business="{{ $t->type === 'firma' ? 1 : 0 }}" @selected((int) old('tenant_id', $inv?->tenant_id) === $t->id)>{{ $t->type === 'firma' ? $t->company_name : $t->display_name }}@if ($t->type === 'firma') (Firma)@endif</option>@endforeach
      </select>
    </div>
    <div data-kind="owner">
      <label for="owner_id">Eigentümer</label>
      <select id="owner_id" name="owner_id" style="{{ $box }}">
        <option value="">Bitte wählen</option>
        @foreach ($owners as $o)<option value="{{ $o->id }}" data-business="{{ trim((string) $o->vat_id) !== '' ? 1 : 0 }}" @selected((int) old('owner_id', $inv?->owner_id) === $o->id)>{{ $o->name }}</option>@endforeach
      </select>
      <small class="sd-muted">Anschrift und E-Mail kommen aus den Stammdaten.</small>
    </div>
    <div data-kind="free" class="iv-grid">
      <div><label for="b_name">Name oder Firma</label><input id="b_name" name="b_name" value="{{ $b('name') }}" style="{{ $box }}"></div>
      <div><label for="b_street">Straße und Hausnummer</label><input id="b_street" name="b_street" value="{{ $b('street') }}" style="{{ $box }}"></div>
      <div class="iv-two"><div><label for="b_zip">PLZ</label><input id="b_zip" name="b_zip" value="{{ $b('zip') }}" style="{{ $box }}"></div><div><label for="b_city">Ort</label><input id="b_city" name="b_city" value="{{ $b('city') }}" style="{{ $box }}"></div></div>
      <div><label for="b_email">E-Mail-Adresse</label><input id="b_email" name="b_email" type="email" value="{{ $b('email') }}" style="{{ $box }}"></div>
      <div><label for="b_vat_id">Umsatzsteuer-ID (falls Unternehmen)</label><input id="b_vat_id" name="b_vat_id" value="{{ $b('vat_id') }}" style="{{ $box }}"></div>
    </div>
    <label><input type="checkbox" name="b_business" value="1" id="b_business" @checked(old('b_business', $buyer['business'] ?? false))> Der Empfänger ist ein <strong>Unternehmen</strong>. Dann erzeugt das CRM zusätzlich die E-Rechnung (XML). Bei Privatpersonen nicht ankreuzen.</label>
  </div>

  <div class="sd-card iv-grid">
    <h2 style="margin:0">Angaben zur Rechnung</h2>
    <div class="iv-two">
      <div><label for="issued_on">Rechnungsdatum (leer = heute)</label><input id="issued_on" name="issued_on" type="date" value="{{ $d('issued_on') }}" style="{{ $box }}"></div>
      <div><label for="due_on">Zahlbar bis (leer = {{ $defaults['due_days'] }} Tage)</label><input id="due_on" name="due_on" type="date" value="{{ $d('due_on') }}" style="{{ $box }}"></div>
    </div>
    <div class="iv-two">
      <div><label for="delivery_from">Leistungsdatum oder Beginn *</label><input id="delivery_from" name="delivery_from" type="date" value="{{ $d('delivery_from') }}" style="{{ $box }}"></div>
      <div><label for="delivery_to">Ende des Zeitraums (freiwillig)</label><input id="delivery_to" name="delivery_to" type="date" value="{{ $d('delivery_to') }}" style="{{ $box }}"></div>
    </div>
    <div><label for="buyer_reference">Referenz des Empfängers (freiwillig, z. B. Bestellnummer)</label><input id="buyer_reference" name="buyer_reference" maxlength="100" value="{{ old('buyer_reference', $inv?->buyer_reference) }}" style="{{ $box }}"></div>
    <div><label for="note">Text auf der Rechnung (freiwillig)</label><textarea id="note" name="note" rows="3" maxlength="2000" style="{{ $box }}">{{ old('note', $inv?->note) }}</textarea></div>
  </div>

  <div class="sd-card">
    <h2 style="margin:0 0 .3rem">Positionen</h2>
    <p class="sd-muted" style="margin:0 0 .4rem">Menge mal Einzelpreis (netto). Bei Steuersatz 0 % muss der Grund der Steuerbefreiung stehen.@if (! $klein) Hausverwaltung: meist 19 %. Beherbergung (Montage-Wohnung): 7 %.@endif</p>
    <div id="lines">
      @foreach ($rows as $i => $r)
        <div class="iv-row">
          <div class="name"><label>Bezeichnung</label><input name="lines[{{ $i }}][name]" value="{{ $r['name'] }}" maxlength="300" style="{{ $box }}"></div>
          <div><label>Menge</label><input name="lines[{{ $i }}][qty]" inputmode="decimal" value="{{ $r['qty'] }}" placeholder="1" style="{{ $box }}"></div>
          <div><label>Einheit</label><select name="lines[{{ $i }}][unit]" style="{{ $box }}">@foreach (Berechnung::UNITS as $k => $label)<option value="{{ $k }}" @selected($r['unit'] === $k)>{{ $label }}</option>@endforeach</select></div>
          <div><label>Preis netto (€)</label><input name="lines[{{ $i }}][price]" inputmode="decimal" value="{{ $r['price'] }}" style="{{ $box }}"></div>
          <div><label>USt</label><select name="lines[{{ $i }}][rate]" style="{{ $box }}" @disabled($klein)>@foreach (Berechnung::RATES as $rt)<option value="{{ $rt }}" @selected((int) $r['rate'] === $rt)>{{ $rt }} %</option>@endforeach</select>@if ($klein)<input type="hidden" name="lines[{{ $i }}][rate]" value="0">@endif</div>
          <div class="full"><label>Grund bei 0 % (Steuerbefreiung)</label><input name="lines[{{ $i }}][exempt]" list="exempt-list" value="{{ $r['exempt'] }}" maxlength="190" style="{{ $box }}"></div>
        </div>
      @endforeach
    </div>
    <datalist id="exempt-list">@foreach (Berechnung::EXEMPTIONS as $x)<option value="{{ $x }}">@endforeach</datalist>
    <button type="button" class="sd-btn sec" id="more" style="margin-top:.6rem">+ Weitere Position</button>
  </div>

  <div class="sd-actions"><button class="sd-btn" type="submit">Entwurf speichern</button> <a class="sd-btn sec" href="{{ $inv ? route('invoices.show', $inv) : route('invoices.index') }}">Abbrechen</a></div>
</form>
<script>
(function(){
  var radios=document.querySelectorAll('input[name=recipient_kind]');
  function show(){var k=document.querySelector('input[name=recipient_kind]:checked');k=k?k.value:'tenant';
    document.querySelectorAll('[data-kind]').forEach(function(el){el.style.display=el.dataset.kind===k?'':'none';});}
  radios.forEach(function(r){r.addEventListener('change',show);});show();
  ['tenant_id','owner_id'].forEach(function(id){var s=document.getElementById(id);
    if(s){s.addEventListener('change',function(){var o=s.options[s.selectedIndex];if(o&&o.dataset.business!==undefined){document.getElementById('b_business').checked=o.dataset.business==='1';}});}});
  var box=document.getElementById('lines'),n=box.children.length;
  document.getElementById('more').addEventListener('click',function(){
    var c=box.children[box.children.length-1].cloneNode(true);
    c.querySelectorAll('input,select').forEach(function(el){
      if(el.name){el.name=el.name.replace(/lines\[\d+\]/,'lines['+n+']');}
      if(el.tagName==='INPUT'&&el.type!=='hidden'){el.value='';}
      if(el.tagName==='SELECT'&&el.name.indexOf('[unit]')>0){el.value='C62';}
      if(el.tagName==='SELECT'&&el.name.indexOf('[rate]')>0){el.value='{{ $klein ? 0 : 19 }}';}
    });
    box.appendChild(c);n++;
  });
})();
</script>
@endsection
