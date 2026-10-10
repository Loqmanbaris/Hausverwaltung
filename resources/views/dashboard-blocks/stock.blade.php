<div class="sd-card">
  <h2>Bestand <span class="sd-muted" style="font-weight:400;font-size:.92rem">· {{ $totals['properties'] }} Objekte · {{ $totals['units'] }} Einheiten · {{ number_format($totals['area'], 0, ',', '.') }} m² · {{ $totals['tenants'] }} Mieter</span></h2>
  <div class="sd-table-wrap">
    <table class="sd">
      <thead>
        <tr><th>Eigentümer</th><th class="db-num">Objekte</th><th class="db-num">Einheiten</th><th class="db-num">Fläche</th><th class="db-num">Mieter</th></tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          <tr>
            <td><a href="{{ route('owners.show', $row['owner']->id) }}">{{ $row['owner']->name }}</a></td>
            <td class="db-num">{{ $row['properties'] }}</td>
            <td class="db-num">{{ $row['units'] }}</td>
            <td class="db-num">{{ number_format($row['area'], 0, ',', '.') }} m²</td>
            <td class="db-num">{{ $row['tenants'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if (count($usage) > 0)
    <h2 class="mt" style="font-size:1rem">Einheiten nach Nutzungsart</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Nutzungsart</th><th class="db-num">Einheiten</th><th class="db-num">Fläche</th></tr></thead>
        <tbody>
          @foreach ($usage as $u)
            <tr>
              <td>{{ $u['label'] }}</td>
              <td class="db-num">{{ $u['units'] }}</td>
              <td class="db-num">{{ number_format($u['area'], 0, ',', '.') }} m²</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
