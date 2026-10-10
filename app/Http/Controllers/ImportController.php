<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Support\Documents\DocumentStore;
use App\Support\Import\Importer;
use App\Support\Import\Vorlage;
use App\Support\Import\XlsxReader;
use Illuminate\Http\Request;
use RuntimeException;

/** Import von Objekten, Einheiten, Mietern, Mietverträgen und Zählern aus Excel (Paket 15). */
class ImportController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        return view('import.index', ['result' => null]);
    }

    public function template()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vorlage');
        Vorlage::build($tmp, $this->me());

        return response()->download($tmp, 'crm-import-vorlage.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function check(Request $request)
    {
        $request->validate(['datei' => ['required', 'file', 'max:5120']], ['datei.required' => 'Bitte die ausgefüllte Excel-Datei auswählen.', 'datei.max' => 'Die Datei darf höchstens 5 MB groß sein.']);
        $raw = (string) file_get_contents($request->file('datei')->getRealPath());
        try {
            $sheets = XlsxReader::read($raw);
        } catch (RuntimeException $e) {
            return back()->withErrors(['datei' => $e->getMessage()]);
        }
        $result = (new Importer($this->me()))->plan($sheets);
        $this->forgetPending($request);
        if ($result['errors'] === [] && array_sum($result['counts']) > 0) {
            $path = DocumentStore::put($raw);
            $request->session()->put('import_pending', ['path' => $path, 'hash' => hash('sha256', $raw), 'name' => $request->file('datei')->getClientOriginalName()]);
        }

        return view('import.index', ['result' => $result, 'fileName' => $request->file('datei')->getClientOriginalName()]);
    }

    public function run(Request $request)
    {
        $pending = $request->session()->get('import_pending');
        if (! $pending || ! DocumentStore::exists($pending['path'])) {
            return redirect()->route('import.index')->withErrors(['datei' => 'Bitte die Datei noch einmal hochladen und prüfen.']);
        }
        $raw = DocumentStore::get($pending['path']);
        $importer = new Importer($this->me());
        $result = $importer->plan(XlsxReader::read($raw));
        if ($result['errors'] !== []) {
            $this->forgetPending($request);

            return view('import.index', ['result' => $result, 'fileName' => $pending['name']]);
        }
        $counts = $importer->run($result['plan']);
        $this->forgetPending($request);

        return redirect()->route('import.index')->with('status', 'Import abgeschlossen: '.$counts['objekte'].' Objekte, '.$counts['einheiten'].' Einheiten, '.$counts['mieter'].' Mieter, '.$counts['vertraege'].' Mietverträge und '.$counts['zaehler'].' Zähler angelegt.');
    }

    private function forgetPending(Request $request): void
    {
        $p = $request->session()->pull('import_pending');
        if ($p && isset($p['path'])) {
            DocumentStore::forget($p['path']);
        }
    }
}
