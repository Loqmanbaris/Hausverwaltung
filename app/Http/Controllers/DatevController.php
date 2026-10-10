<?php

namespace App\Http\Controllers;

use App\Models\DatevExport;
use App\Models\Owner;
use App\Support\Audit;
use App\Support\Datev\DatevAccounts;
use App\Support\Datev\DatevConfig;
use App\Support\Datev\DatevService;
use App\Support\Datev\ReceiptArchive;
use App\Support\Documents\DocumentStore;
use App\Support\Kostenarten;
use App\Support\Settings;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DATEV-Export der Belege (nur Administrator): Einstellungen, Konten der Kostenarten, Vorschau und Datei.
 * Die erzeugte Datei bleibt verschlüsselt gespeichert und kann später wieder heruntergeladen werden.
 */
class DatevController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesOwners;

    public function index(Request $request)
    {
        $config = DatevConfig::load();
        $problems = DatevConfig::problems($config);

        $accounts = DatevAccounts::all();
        $types = Kostenarten::all(true);
        $withoutAccount = array_values(array_filter($types, fn ($label, $key) => trim((string) ($accounts[$key]['account'] ?? '')) === '', ARRAY_FILTER_USE_BOTH));

        // Zeitraum: Vorgabe ist der vorige Monat
        $firstOfMonth = new DateTimeImmutable('first day of this month');
        $from = $this->parseDate($request->query('von')) ?? $firstOfMonth->modify('-1 month');
        $to = $this->parseDate($request->query('bis')) ?? $firstOfMonth->modify('-1 day');
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        $again = $request->boolean('nochmal');

        $inputErrors = $this->periodErrors($from, $to, $config);
        $preview = null;
        if ($request->boolean('vorschau') && $inputErrors === []) {
            $preview = DatevService::plan($from, $to, $ownerId, $again);
            $preview['sums'] = $this->sumsByAccount($preview['bookings']);
            $preview['total'] = $this->signedTotal($preview['bookings']);
        }

        $changed = DB::table('datev_export_items as i')
            ->join('datev_exports as e', 'e.id', '=', 'i.datev_export_id')
            ->join('receipts as r', 'r.id', '=', 'i.receipt_id')
            ->whereColumn('r.updated_at', '>', 'e.created_at')
            ->select('r.id', 'r.deleted_at')
            ->distinct()
            ->orderByDesc('r.id')
            ->limit(30)
            ->get();

        return view('datev.index', [
            'config' => $config,
            'problems' => $problems,
            'withoutAccount' => $withoutAccount,
            'from' => $from,
            'to' => $to,
            'ownerId' => $ownerId,
            'again' => $again,
            'owners' => Owner::query()->orderBy('name')->get(['id', 'name']),
            'inputErrors' => $inputErrors,
            'preview' => $preview,
            'exports' => DatevExport::query()->with(['creator', 'owner'])->orderByDesc('id')->limit(20)->get(),
            'changed' => $changed,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'von' => ['required', 'date_format:Y-m-d'],
            'bis' => ['required', 'date_format:Y-m-d'],
            'eigentuemer' => ['nullable', 'integer'],
            'nochmal' => ['nullable', 'boolean'],
        ], [
            'von.required' => 'Bitte den Beginn des Zeitraums angeben.',
            'bis.required' => 'Bitte das Ende des Zeitraums angeben.',
            'von.date_format' => 'Das Datum „von“ ist ungültig.',
            'bis.date_format' => 'Das Datum „bis“ ist ungültig.',
        ]);

        $from = $this->parseDate($request->input('von'));
        $to = $this->parseDate($request->input('bis'));
        $ownerId = (int) $request->input('eigentuemer') ?: null;
        $again = $request->boolean('nochmal');
        $back = ['von' => $request->input('von'), 'bis' => $request->input('bis'), 'eigentuemer' => $ownerId, 'nochmal' => $again ? 1 : null, 'vorschau' => 1];

        $config = DatevConfig::load();
        $errors = $from && $to ? $this->periodErrors($from, $to, $config) : ['Der Zeitraum ist ungültig.'];
        if ($errors !== []) {
            return redirect()->route('datev.index', array_filter($back))->withErrors($errors);
        }

        $plan = DatevService::plan($from, $to, $ownerId, $again);
        if ($plan['problems'] !== []) {
            return redirect()->route('datev.index', array_filter($back))->withErrors($plan['problems']);
        }
        if ($plan['bookings'] === []) {
            return redirect()->route('datev.index', array_filter($back))->withErrors(['Für diesen Zeitraum gibt es keine Buchungen, die exportiert werden können.']);
        }

        try {
            $export = DatevService::create($plan, $from, $to, $ownerId, $this->me());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('datev.index', array_filter($back))->withErrors([$e->getMessage()]);
        } catch (RuntimeException $e) {
            return redirect()->route('datev.index', array_filter($back))->withErrors([$e->getMessage()]);
        }

        $note = '';
        if ($plan['skipped'] !== []) {
            $note = ' '.count($plan['skipped']).' Beleg'.(count($plan['skipped']) === 1 ? ' wurde' : 'e wurden').' nicht übernommen (siehe Vorschau).';
        }

        return redirect()->route('datev.index')->with('status', 'Die Datei wurde erstellt: '.$export->entry_count.' Buchungen. Du findest sie unten unter „Bisherige Exporte“.'.$note);
    }

    public function download(Request $request, DatevExport $datevExport)
    {
        try {
            $plain = DocumentStore::get($datevExport->storage_path);
        } catch (RuntimeException $e) {
            Audit::log('document.failed', $datevExport, 'DATEV-Datei nicht lesbar: '.$e->getMessage());
            abort(500, 'Die Datei kann nicht gelesen werden. Bitte den Administrator informieren.');
        }

        if (! hash_equals((string) $datevExport->sha256, hash('sha256', $plain))) {
            Audit::log('document.failed', $datevExport, 'Prüfsumme der DATEV-Datei stimmt nicht: '.$datevExport->filename);
            abort(500, 'Die Datei ist verändert worden und wird nicht ausgeliefert.');
        }

        Audit::log('datev.downloaded', $datevExport, 'DATEV-Datei heruntergeladen: '.$datevExport->filename);

        return response($plain, 200, [
            'Content-Type' => 'text/csv; charset=windows-1252',
            'Content-Length' => (string) strlen($plain),
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $datevExport->filename).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Alle Belege eines Exports als ZIP-Datei, dazu eine Belegliste. Wird bei jedem Abruf neu zusammengestellt. */
    public function receipts(Request $request, DatevExport $datevExport)
    {
        try {
            $built = ReceiptArchive::build($datevExport);
        } catch (RuntimeException $e) {
            Audit::log('document.failed', $datevExport, 'Belege zum DATEV-Export nicht lieferbar: '.$e->getMessage());
            abort(500, $e->getMessage());
        }

        Audit::log('datev.receipts_downloaded', $datevExport, 'Belege zum DATEV-Export heruntergeladen: '.$built['count'].' Dateien ('.$datevExport->filename.')');

        return response()->download($built['path'], $built['name'], [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    public function settings()
    {
        return view('datev.settings', ['config' => DatevConfig::load()]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'consultant' => ['required', 'regex:/^[1-9]\d{3,6}$/'],
            'client' => ['required', 'regex:/^[1-9]\d{0,4}$/'],
            'skr' => ['required', 'in:03,04'],
            'fy_month' => ['required', 'integer', 'between:1,12'],
            'account_length' => ['required', 'integer', 'between:4,8'],
            'counter_account' => ['required', 'regex:/^\d{4,9}$/'],
        ], [
            'consultant.required' => 'Bitte die Beraternummer eintragen.',
            'consultant.regex' => 'Die Beraternummer hat 4 bis 7 Ziffern.',
            'client.required' => 'Bitte die Mandantennummer eintragen.',
            'client.regex' => 'Die Mandantennummer hat 1 bis 5 Ziffern.',
            'skr.in' => 'Bitte SKR03 oder SKR04 wählen.',
            'counter_account.required' => 'Bitte das Gegenkonto eintragen.',
            'counter_account.regex' => 'Das Gegenkonto besteht nur aus Ziffern (4 bis 9).',
        ]);

        if (! DatevConfig::validCounterAccount($data['counter_account'], (int) $data['account_length'])) {
            return back()->withInput()->withErrors(['counter_account' => 'Das Gegenkonto braucht '.$data['account_length'].' Ziffern (Sachkonto) oder '.((int) $data['account_length'] + 1).' Ziffern (Personenkonto).']);
        }

        $old = DatevConfig::load();
        $new = [
            'consultant' => $data['consultant'],
            'client' => $data['client'],
            'skr' => $data['skr'],
            'fy_month' => (string) (int) $data['fy_month'],
            'account_length' => (string) (int) $data['account_length'],
            'counter_account' => $data['counter_account'],
        ];

        DB::transaction(function () use ($old, $new) {
            Settings::putMany([
                'datev_consultant' => $new['consultant'],
                'datev_client' => $new['client'],
                'datev_skr' => $new['skr'],
                'datev_fy_month' => $new['fy_month'],
                'datev_account_length' => $new['account_length'],
                'datev_counter_account' => $new['counter_account'],
            ]);

            [$before, $after] = Audit::diff(array_map('strval', $old), $new);
            if ($after !== null) {
                Audit::log('datev.settings', 'DATEV-Einstellungen geändert', null, $before, $after);
            }
        });

        return redirect()->route('datev.index')->with('status', 'Die DATEV-Einstellungen wurden gespeichert.');
    }

    public function accounts()
    {
        $config = DatevConfig::load();
        $active = Kostenarten::all(true);
        $inactive = array_diff_key(Kostenarten::all(false), $active);

        return view('datev.accounts', [
            'config' => $config,
            'groups' => Kostenarten::groups(),
            'inactive' => $inactive,
            'accounts' => DatevAccounts::all(),
        ]);
    }

    public function saveAccounts(Request $request)
    {
        $config = DatevConfig::load();
        $known = Kostenarten::all(false);

        $accountInput = (array) $request->input('account', []);
        $buInput = (array) $request->input('bu', []);
        $rows = [];
        $errors = [];

        foreach ($known as $key => $label) {
            $account = trim((string) ($accountInput[$key] ?? ''));
            $bu = trim((string) ($buInput[$key] ?? ''));

            if ($account !== '' && ! DatevConfig::validAccount($account, $config['account_length'])) {
                $errors[] = 'Konto bei „'.$label.'“: Es braucht genau '.$config['account_length'].' Ziffern.';
            }
            if ($bu !== '' && preg_match('/^\d{1,4}$/', $bu) !== 1) {
                $errors[] = 'Steuerschlüssel bei „'.$label.'“: Es sind 1 bis 4 Ziffern möglich.';
            }
            $rows[$key] = ['account' => $account, 'bu_key' => $bu];
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $changed = DatevAccounts::save($rows);

        return redirect()->route('datev.accounts')->with('status', $changed === 0 ? 'Es gab keine Änderungen.' : 'Die Konten wurden gespeichert ('.$changed.' geändert).');
    }

    // ---------------------------------------------------------------- Hilfen

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        $v = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);

        return ($d !== false && $d->format('Y-m-d') === $v) ? $d : null;
    }

    /** @return string[] */
    private function periodErrors(DateTimeImmutable $from, DateTimeImmutable $to, array $config): array
    {
        $errors = [];
        if ($from > $to) {
            $errors[] = 'Das Ende des Zeitraums liegt vor dem Beginn.';
        } elseif ($config['fy_month'] >= 1 && $config['fy_month'] <= 12) {
            $start = DatevConfig::fiscalYearStart($from, $config['fy_month']);
            if ($to > DatevConfig::fiscalYearEnd($start)) {
                $errors[] = 'Der Zeitraum geht über einen Jahreswechsel des Wirtschaftsjahres hinweg. DATEV übernimmt nur Tag und Monat des Belegdatums, deshalb bitte je Wirtschaftsjahr getrennt exportieren.';
            }
        }

        return $errors;
    }

    /** @return array<string, float> Konto => Saldo (Soll positiv, Haben negativ) */
    private function sumsByAccount(array $bookings): array
    {
        $sums = [];
        foreach ($bookings as $b) {
            $sums[$b['account']] = ($sums[$b['account']] ?? 0.0) + ($b['side'] === 'H' ? -1 : 1) * (float) $b['amount'];
        }
        ksort($sums);

        return $sums;
    }

    private function signedTotal(array $bookings): float
    {
        $t = 0.0;
        foreach ($bookings as $b) {
            $t += ($b['side'] === 'H' ? -1 : 1) * (float) $b['amount'];
        }

        return $t;
    }
}
