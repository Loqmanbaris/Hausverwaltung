<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Support\Contracts\ContractData;
use App\Support\Contracts\ContractService;
use App\Support\Documents\DocumentStore;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Mietvertrag als Dokument: erstellen, ansehen, drucken, unterschreiben. */
class LeaseContractController extends Controller
{
    use ScopesOwners;

    public function create(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'owner']);
        $extras = ContractData::defaultExtras($lease);

        return view('contracts.form', $this->formData($lease, $extras, null));
    }

    public function store(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $extras = $this->extras($request, ContractData::kindFor($lease));
        $c = ContractService::create($lease, $extras, $this->me());

        return redirect()->route('contracts.show', $c)->with('status', 'Vertrag erstellt. Bitte durchlesen, dann drucken oder am Tablet unterschreiben.');
    }

    public function show(LeaseContract $contract)
    {
        $this->access($contract);
        $contract->load('lease.unit.property', 'lease.tenant');
        $values = ContractData::values($contract->lease, $contract->extras ?? []);

        return view('contracts.show', [
            'c' => $contract,
            'paper' => ContractData::needsPaper($contract->lease, $values),
            'sig' => $this->signatures($contract),
        ]);
    }

    public function edit(LeaseContract $contract)
    {
        $this->access($contract);
        abort_unless($contract->isDraft(), 403, 'Nur Entwürfe können geändert werden.');
        $contract->load('lease.unit.property', 'lease.tenant', 'lease.owner');

        return view('contracts.form', $this->formData($contract->lease, $contract->extras ?? [], $contract));
    }

    public function update(Request $request, LeaseContract $contract)
    {
        $this->access($contract);
        try {
            ContractService::regenerate($contract, $this->extras($request, $contract->kind));
        } catch (RuntimeException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag neu erstellt.');
    }

    public function print(LeaseContract $contract)
    {
        $this->access($contract);

        return view('contracts.print', ['c' => $contract, 'sig' => $this->signatures($contract)]);
    }

    public function signForm(LeaseContract $contract)
    {
        $this->access($contract);
        abort_unless($contract->isDraft(), 404);
        $contract->load('lease.unit.property', 'lease.tenant', 'lease.owner');
        $values = ContractData::values($contract->lease, $contract->extras ?? []);

        return view('contracts.sign', [
            'c' => $contract,
            'paper' => ContractData::needsPaper($contract->lease, $values),
            'tenantName' => trim(($contract->header['mieter']['vertreter'] ?? '') !== '' ? $contract->header['mieter']['vertreter'].' für '.$contract->header['mieter']['name'] : ($contract->header['mieter']['name'] ?? '')),
            'landlordName' => $this->me()->name,
        ]);
    }

    public function signStore(Request $request, LeaseContract $contract)
    {
        $this->access($contract);
        $data = $request->validate([
            'read' => ['accepted'],
            'place' => ['required', 'string', 'max:120'],
            'tenant_name' => ['required', 'string', 'max:190'],
            'landlord_name' => ['required', 'string', 'max:190'],
            'tenant_signature' => ['required', 'string', 'max:900000'],
            'landlord_signature' => ['required', 'string', 'max:900000'],
            'hash' => ['required', 'string', 'size:64'],
        ], [
            'read.accepted' => 'Bitte bestätigen, dass der Vertrag gelesen wurde.',
            'place.required' => 'Bitte den Ort angeben.',
            'tenant_name.required' => 'Bitte den Namen des Mieters angeben.',
            'landlord_name.required' => 'Bitte den Namen des Vermieters angeben.',
            'tenant_signature.required' => 'Die Unterschrift des Mieters fehlt.',
            'landlord_signature.required' => 'Die Unterschrift des Vermieters fehlt.',
        ]);
        if (! hash_equals((string) $contract->content_hash, $data['hash'])) {
            return back()->withErrors(['contract' => 'Der Vertrag wurde inzwischen geändert. Bitte die Seite neu laden und noch einmal lesen.']);
        }
        try {
            ContractService::signOnTablet($contract, $data['tenant_signature'], $data['landlord_signature'], $data['tenant_name'], $data['landlord_name'], $data['place'], $this->me());
        } catch (RuntimeException $e) {
            return back()->withInput($request->except(['tenant_signature', 'landlord_signature']))->withErrors(['contract' => $e->getMessage()]);
        }

        \App\Support\Ablage\Ablage::fileFinished('vertrag', (int) $contract->id, $this->me());

        return redirect()->route('contracts.show', $contract)->with('status', 'Der Vertrag ist unterschrieben. Bitte jetzt für beide Seiten drucken oder als PDF speichern.');
    }

    public function scanStore(Request $request, LeaseContract $contract)
    {
        $this->access($contract);
        $request->validate(['scan' => ['required', 'file', 'max:20480']], ['scan.required' => 'Bitte eine Datei auswählen.', 'scan.max' => 'Die Datei darf höchstens 20 MB groß sein.']);
        try {
            ContractService::attachScan($contract, $request->file('scan'), $contract->header['ort'] ?? null, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['scan' => $e->getMessage()]);
        }

        return redirect()->route('contracts.show', $contract)->with('status', 'Unterschriebener Vertrag abgelegt.');
    }

    public function scanShow(LeaseContract $contract)
    {
        $this->access($contract);
        abort_unless($contract->scan_path, 404);
        $raw = DocumentStore::get($contract->scan_path);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$contract->scan_mime] ?? 'bin';

        return response($raw, 200, [
            'Content-Type' => $contract->scan_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="mietvertrag-'.$contract->id.'.'.$ext.'"',
        ]);
    }

    public function destroy(LeaseContract $contract)
    {
        $this->access($contract);
        abort_unless($contract->isDraft(), 403, 'Nur Entwürfe können gelöscht werden.');
        $leaseId = $contract->lease_id;
        ContractService::delete($contract);

        return redirect()->to(route('leases.show', $leaseId).'#vertragsdokument')->with('status', 'Entwurf gelöscht.');
    }

    public function cancel(LeaseContract $contract)
    {
        $this->access($contract);
        abort_if($contract->status === 'storniert', 404);
        ContractService::cancel($contract);

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag storniert. Er bleibt zur Nachverfolgung gespeichert.');
    }

    /* ---------- Hilfen ---------- */

    private function access(LeaseContract $c): void
    {
        $this->assertOwnerAccess((int) $c->owner_id);
    }

    private function signatures(LeaseContract $c): array
    {
        return [
            'tenant' => ContractService::signatureDataUri($c->tenant_signature),
            'landlord' => ContractService::signatureDataUri($c->landlord_signature),
        ];
    }

    private function formData(Lease $lease, array $extras, ?LeaseContract $contract): array
    {
        $kind = ContractData::kindFor($lease);
        $values = ContractData::values($lease, $extras);

        return [
            'lease' => $lease,
            'contract' => $contract,
            'kind' => $kind,
            'fields' => ContractData::extrasFor($kind),
            'extras' => $extras,
            'values' => $values,
            'warnings' => ContractData::warnings($lease, $values),
        ];
    }

    private function extras(Request $request, string $kind): array
    {
        $fields = ContractData::extrasFor($kind);
        $rules = [];
        foreach ($fields as $key => [$label, $type]) {
            $rules['x.'.$key] = match ($type) {
                'textarea' => ['nullable', 'string', 'max:3000'],
                'check' => ['nullable', 'boolean'],
                'number' => ['nullable', 'integer', 'between:0,'.($key === 'kuendigungsverzicht' ? 4 : 50)],
                default => ['nullable', 'string', 'max:500'],
            };
        }
        $data = $request->validate($rules, [
            'x.kuendigungsverzicht.between' => 'Ein Kündigungsverzicht ist bei Wohnraum höchstens für 4 Jahre möglich.',
            'x.*.integer' => 'Bitte eine ganze Zahl eingeben.',
            'x.*.max' => 'Der Text ist zu lang.',
        ]);
        $x = $data['x'] ?? [];
        $out = [];
        foreach ($fields as $key => [$label, $type]) {
            $v = $x[$key] ?? null;
            if ($type === 'check') {
                $out[$key] = (bool) $v;
            } elseif ($type === 'money') {
                $s = trim((string) $v);
                if ($s !== '' && Money::parse($s) === null) {
                    throw ValidationException::withMessages(['x.'.$key => '„'.$label.'“: Bitte einen gültigen Betrag eingeben.']);
                }
                $out[$key] = $s === '' ? '' : Money::parse($s);
            } else {
                $out[$key] = trim((string) $v);
            }
        }

        return $out;
    }
}
