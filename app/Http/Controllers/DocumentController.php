<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Document;
use App\Support\Ablage\Ablage;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Documents\UploadInspector;
use App\Support\Documents\UploadRejected;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Dokumentenablage (Paket 22). */
class DocumentController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $cat = (string) $request->query('kategorie');
        $frist = (string) $request->query('frist');
        $q = trim((string) $request->query('q'));
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $today = date('Y-m-d');
        $docs = Document::visibleTo($this->me())
            ->when($ownerId, fn ($w) => $w->where('documents.owner_id', $ownerId))
            ->when(isset(Ablage::CATEGORIES[$cat]), fn ($w) => $w->where('category', $cat))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('original_name', 'like', '%'.$q.'%')->orWhere('note', 'like', '%'.$q.'%')))
            ->when($frist === 'bald', fn ($w) => $w->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<=', date('Y-m-d', strtotime('+90 days'))))
            ->when($frist === 'abgelaufen', fn ($w) => $w->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<', $today))
            ->when($request->query('archiv') !== '1', fn ($w) => $w->where('archived', false))
            ->orderByRaw($frist !== '' ? 'expires_on asc' : 'documents.id desc')
            ->simplePaginate(50)->withQueryString();

        $targets = [];
        foreach ($docs as $d) {
            $targets[$d->id] = Ablage::target($d->attach_type, (int) $d->attach_id);
        }

        return view('documents.index', [
            'docs' => $docs, 'targets' => $targets, 'cat' => $cat, 'frist' => $frist, 'q' => $q, 'ownerId' => $ownerId,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'counts' => [
                'abgelaufen' => Document::visibleTo($this->me())->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<', $today)->count(),
                'bald' => Document::visibleTo($this->me())->where('archived', false)->whereNotNull('expires_on')->whereBetween('expires_on', [$today, date('Y-m-d', strtotime('+90 days'))])->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attach_type' => ['required', Rule::in(array_keys(Ablage::TYPES))],
            'attach_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(Ablage::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:190'],
            'doc_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'remind_days' => ['nullable', 'integer', 'between:1,365'],
            'note' => ['nullable', 'string', 'max:2000'],
            'files' => ['required', 'array', 'min:1', 'max:'.UploadInspector::MAX_FILES],
            'files.*' => ['file'],
        ], [
            'files.required' => 'Bitte mindestens eine Datei auswählen.',
            'files.max' => 'Höchstens '.UploadInspector::MAX_FILES.' Dateien auf einmal.',
            'remind_days.between' => 'Erinnerung: 1 bis 365 Tage vorher.',
        ]);
        $t = Ablage::target($data['attach_type'], (int) $data['attach_id']);
        abort_if($t === null, 404);
        $this->assertOwnerAccess($t[1]);
        if (! in_array($data['attach_type'], Ablage::CATEGORIES[$data['category']][1], true)) {
            return back()->withInput()->withErrors(['category' => 'Diese Art passt hier nicht. Bitte eine andere wählen.']);
        }

        $files = $request->file('files');
        $saved = [];
        $errors = [];
        foreach ($files as $f) {
            try {
                $info = UploadInspector::inspect($f->getRealPath(), $f->getClientOriginalName());
                $meta = $data;
                if (count($files) > 1 && ! empty($data['title'])) {
                    $meta['title'] = $data['title'].' ('.(count($saved) + 1).')';
                }
                $saved[] = Ablage::store($data['attach_type'], (int) $data['attach_id'], $info, $meta, $this->me());
            } catch (UploadRejected $e) {
                $errors[] = $e->getMessage();
            }
        }
        foreach ($saved as $doc) {
            Audit::log('document.created', $doc, 'Dokument abgelegt: '.$doc->title.' ('.$t[2].')');
        }
        $msg = count($saved) === 0 ? null : (count($saved) === 1 ? 'Dokument abgelegt.' : count($saved).' Dokumente abgelegt.');
        $r = redirect()->to($t[3]);
        if ($msg) {
            $r = $r->with('status', $msg);
        }

        return $errors === [] ? $r : $r->withErrors(['files' => implode(' ', $errors)])->withInput();
    }

    public function file(Request $request, Document $document)
    {
        $this->assertOwnerAccess((int) $document->owner_id);
        $inline = ! $request->boolean('download') && ($document->mime === 'application/pdf' || $document->isImage());
        $name = str_replace(['"', "\r", "\n"], '', $document->original_name);

        return response(DocumentStore::get($document->path), 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Document $document)
    {
        $this->assertOwnerAccess((int) $document->owner_id);

        return view('documents.edit', ['doc' => $document, 'target' => Ablage::target($document->attach_type, (int) $document->attach_id)]);
    }

    public function update(Request $request, Document $document)
    {
        $this->assertOwnerAccess((int) $document->owner_id);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ablage::categoriesFor($document->attach_type)))],
            'title' => ['required', 'string', 'max:190'],
            'doc_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'remind_days' => ['nullable', 'integer', 'between:1,365'],
            'note' => ['nullable', 'string', 'max:2000'],
            'archived' => ['nullable', 'in:1'],
            'shared_with_tenant' => ['nullable', 'in:1'],
        ]);
        $newExpiry = $data['expires_on'] ?? null;
        $old = $document->expires_on?->format('Y-m-d');
        $document->fill([
            'category' => $data['category'], 'title' => $data['title'], 'doc_date' => $data['doc_date'] ?? null,
            'expires_on' => $newExpiry, 'remind_days' => (int) ($data['remind_days'] ?? 30) ?: 30, 'note' => $data['note'] ?? null,
            'archived' => isset($data['archived']),
            'shared_with_tenant' => isset($data['shared_with_tenant']) && in_array($document->attach_type, ['tenant', 'lease'], true),
        ]);
        if ($old !== ($newExpiry ? date('Y-m-d', strtotime($newExpiry)) : null) || $document->isDirty('remind_days')) {
            $document->reminded_at = null;
        }
        $document->save();
        Audit::log('document.updated', $document, 'Dokument geändert: '.$document->title);
        $t = Ablage::target($document->attach_type, (int) $document->attach_id);

        return redirect()->to($t[3] ?? route('documents.index'))->with('status', 'Gespeichert.');
    }

    public function archive(Document $document)
    {
        $this->assertOwnerAccess((int) $document->owner_id);
        $document->update(['archived' => ! $document->archived]);

        return back()->with('status', $document->archived ? 'Ins Archiv verschoben. Es erscheint nicht mehr bei den Fristen.' : 'Wieder aktiv.');
    }

    public function destroy(Document $document)
    {
        $this->assertOwnerAccess((int) $document->owner_id);
        $t = Ablage::target($document->attach_type, (int) $document->attach_id);
        Ablage::delete($document);

        return redirect()->to($t[3] ?? route('documents.index'))->with('status', 'Dokument gelöscht.');
    }
}
