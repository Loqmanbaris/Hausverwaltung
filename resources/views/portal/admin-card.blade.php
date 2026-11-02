@php
  $pu = \Illuminate\Support\Facades\Schema::hasTable('tenant_portal_users') ? \App\Models\TenantPortalUser::query()->where('tenant_id', $tenant->id)->first() : null;
  $defaultMail = $pu?->email ?: $tenant->email;
@endphp
<div class="sd-card" id="portal">
  <h2>Mieterportal</h2>
  @if ($errors->has('portal'))<div class="sd-err">{{ $errors->first('portal') }}</div>@endif
  @if ($pu && $pu->enabled)
    <p style="margin:0 0 .4rem">Freigegeben für <strong>{{ $pu->email }}</strong>. @if ($pu->last_login_at)Zuletzt angemeldet am {{ $pu->last_login_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr.@else Noch nie angemeldet.@endif</p>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
      <form method="POST" action="{{ route('portal.admin.invite', $tenant) }}">@csrf<button class="sd-btn small" type="submit">Einladung per E-Mail senden</button></form>
      <form method="POST" action="{{ route('portal.admin.disable', $tenant) }}" onsubmit="return confirm('Portal für diesen Mieter sperren?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Sperren</button></form>
    </div>
    <p class="sd-muted" style="margin:.6rem 0 0">Der Mieter sieht sein Mieterkonto und Dokumente, die du bei der Ablage mit „Im Mieterportal sichtbar“ markierst. Er meldet Zählerstände und Schäden selbst.</p>
  @else
    <p class="sd-muted" style="margin-top:0">Der Mieter kann sich ohne Passwort per Link anmelden, sein Konto und freigegebene Dokumente sehen, Zählerstände und Schäden melden. Adresse, an die der Anmelde-Link geht:</p>
    <form method="POST" action="{{ route('portal.admin.enable', $tenant) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
      @csrf
      <div style="flex:1 1 16rem"><label for="pa-mail" class="sd-muted">E-Mail des Mieters</label><input id="pa-mail" type="email" name="email" required value="{{ old('email', $defaultMail) }}" style="padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%"></div>
      <label style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" name="invite" value="1" checked> Gleich einladen</label>
      <button class="sd-btn" type="submit">Portal freigeben</button>
    </form>
  @endif
</div>
