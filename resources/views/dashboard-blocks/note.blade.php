<div class="sd-card" id="merkzettel">
  <h2>Merkzettel</h2>
  <form method="POST" action="{{ route('dashboard.note') }}">
    @csrf
    @method('PUT')
    <textarea name="note" rows="6" maxlength="{{ \App\Support\Dashboard\Layout::NOTE_MAX }}" placeholder="Eigene Notizen, z. B. Anrufe, Erinnerungen, To-dos. Nur für dich sichtbar." style="width:100%;font:inherit;padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:8px;resize:vertical">{{ old('note', $note) }}</textarea>
    @error('note')<div class="sd-err">{{ $message }}</div>@enderror
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;gap:.5rem">
      <span class="sd-muted" style="font-size:.85rem">Nur für dich sichtbar</span>
      <button class="sd-btn sec small" type="submit">Speichern</button>
    </div>
  </form>
</div>
