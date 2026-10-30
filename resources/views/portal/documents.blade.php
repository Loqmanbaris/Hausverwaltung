@extends('portal.layout')
@section('title', 'Dokumente')
@section('content')
<h1>Ihre Dokumente</h1>
@if ($docs->isEmpty())
  <p class="sd-muted">Zurzeit liegen hier keine Dokumente für Sie bereit.</p>
@else
  <div class="sd-card"><table><tbody>
    @foreach ($docs as $d)
      <tr><td>{{ $d->title }}<br><span class="sd-muted">{{ $d->categoryLabel() }}{{ $d->doc_date ? ' · '.$d->doc_date->format('d.m.Y') : '' }}</span></td><td class="n"><a class="sd-btn sec small" href="{{ route('portal.document', $d->id) }}" target="_blank" rel="noopener">Öffnen</a></td></tr>
    @endforeach
  </tbody></table></div>
@endif
@endsection
