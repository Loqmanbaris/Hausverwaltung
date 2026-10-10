<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Support\Documents\UploadInspector;
use App\Support\EInvoice\EInvoiceException;
use App\Support\EInvoice\EInvoiceImporter;
use App\Support\Receipts\ReceiptRules;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * E-Rechnungen empfangen: XRechnung (XML) oder ZUGFeRD/Factur-X (PDF) hochladen.
 * Aus jeder Datei entsteht ein Beleg, dessen Angaben aus der Rechnung gelesen werden.
 * Mitarbeiter dürfen nur für die ihnen freigegebenen Eigentümer Rechnungen ablegen.
 */
class EInvoiceController extends Controller
{
    use ScopesOwners;

    public function create(Request $request)
    {
        $owners = $this->visibleOwners()->get(['owners.id', 'owners.name']);
        $ownerIds = $owners->pluck('id')->map(fn ($id) => (int) $id)->all();

        $properties = Property::query()
            ->whereIn('owner_id', $ownerIds)
            ->orderBy('name')
            ->get(['id', 'owner_id', 'name']);

        return view('receipts.einvoice', [
            'owners' => $owners,
            'properties' => $properties,
            'results' => session('einvoice_results', []),
            'maxFiles' => UploadInspector::MAX_FILES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($this->visibleOwnerIds())],
            'owner_level' => ['nullable', 'boolean'],
            'property_id' => ['nullable', 'integer'],
            'files' => ['required', 'array', 'min:1', 'max:'.UploadInspector::MAX_FILES],
            'files.*' => ['file'],
        ], [
            'owner_id.required' => 'Bitte einen Eigentümer wählen.',
            'owner_id.in' => 'Bitte einen Eigentümer aus der Liste wählen.',
            'files.required' => 'Bitte mindestens eine E-Rechnung auswählen.',
            'files.array' => 'Die Dateien konnten nicht gelesen werden.',
            'files.min' => 'Bitte mindestens eine E-Rechnung auswählen.',
            'files.max' => 'Es sind höchstens :max Dateien auf einmal möglich.',
            'files.*.file' => 'Eine Datei konnte nicht hochgeladen werden. Vermutlich ist sie zu groß (höchstens 10 MB).',
            'files.*.uploaded' => 'Eine Datei konnte nicht hochgeladen werden. Vermutlich ist sie zu groß (höchstens 10 MB).',
        ]);

        // Objekt und Eigentümer müssen zusammenpassen (gleiche Prüfung wie beim Beleg)
        $target = ReceiptRules::normalize([
            'owner_id' => $data['owner_id'],
            'owner_level' => $data['owner_level'] ?? false,
            'property_id' => $data['property_id'] ?? null,
        ]);
        $target = [
            'owner_id' => $target['owner_id'],
            'owner_level' => $target['owner_level'],
            'property_id' => $target['property_id'],
        ];

        $results = [];
        $last = null;
        foreach ((array) $request->file('files', []) as $file) {
            $name = (string) $file->getClientOriginalName();
            try {
                $prepared = EInvoiceImporter::prepare((string) $file->getRealPath(), $name);
                $done = EInvoiceImporter::import($prepared, $target, $this->me());
                $receipt = $done['receipt'];

                $warnings = $done['warnings'];
                if ($done['duplicate'] !== null) {
                    $warnings[] = 'Dieselbe Datei wurde schon bei Beleg Nr. '.$done['duplicate']->id.' erfasst.';
                }
                if ($done['duplicate_invoice'] !== null) {
                    $warnings[] = 'Dieselbe Rechnungsnummer von diesem Lieferanten gibt es schon bei Beleg Nr. '.$done['duplicate_invoice']->id.'. Bitte prüfen, ob die Rechnung doppelt ist.';
                }

                $last = $receipt;
                $results[] = [
                    'ok' => true,
                    'name' => $name,
                    'receipt_id' => $receipt->id,
                    'title' => $receipt->title(),
                    'incomplete' => $receipt->missing(),
                    'warnings' => $warnings,
                ];
            } catch (EInvoiceException $e) {
                $results[] = ['ok' => false, 'name' => $name, 'message' => $e->getMessage()];
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                report($e);
                $results[] = ['ok' => false, 'name' => $name, 'message' => 'Die Datei konnte nicht verarbeitet werden. Bitte noch einmal versuchen. Wenn es wieder nicht klappt, bitte den Administrator informieren.'];
            }
        }

        $okCount = count(array_filter($results, fn ($r) => $r['ok']));

        // Genau eine Datei und alles gut: direkt zum Beleg
        if (count($results) === 1 && $okCount === 1 && $last !== null) {
            $redirect = redirect()->route('receipts.show', $last)->with('status', 'E-Rechnung wurde eingelesen und als Beleg gespeichert.');
            if ($results[0]['warnings'] !== []) {
                $redirect->with('warning', 'Bitte prüfen: '.implode(' ', $results[0]['warnings']));
            }

            return $redirect;
        }

        return redirect()
            ->route('einvoice.create', array_filter(['eigentuemer' => $target['owner_id']]))
            ->with('einvoice_results', $results)
            ->with('status', $okCount === 0 ? null : ($okCount === 1 ? '1 E-Rechnung wurde gespeichert.' : $okCount.' E-Rechnungen wurden gespeichert.'));
    }
}
