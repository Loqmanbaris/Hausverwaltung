<?php

namespace App\Http\Controllers;

use App\Models\DatevExport;
use App\Models\Owner;
use App\Models\RentPayment;
use App\Support\Audit;
use App\Support\Datev\DatevConfig;
use App\Support\Datev\DatevRentAccounts;
use App\Support\Datev\DatevRentService;
use App\Support\Settings;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** DATEV-Export der Mieteinnahmen (nur Administrator, Paket 5c). */
class DatevRentController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesOwners;

    public function index(Request $request)
    {
        $config = DatevConfig::load();
        $first = new DateTimeImmutable('first day of this month');
        $from = $this->date($request->query('von')) ?? $first->modify('-1 month');
        $to = $this->date($request->query('bis')) ?? $first->modify('-1 day');
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        $again = $request->boolean('nochmal');

        $errors = $this->periodErrors($from, $to, $config);
        $preview = null;
        if ($request->boolean('vorschau') && $errors === []) {
            $preview = DatevRentService::plan($from, $to, $ownerId, $again);
            $sums = [];
            foreach ($preview['bookings'] as $b) {
                $sums[$b['account']] = ($sums[$b['account']] ?? 0.0) + ($b['side'] === 'H' ? 1 : -1) * (float) $b['amount'];
            }
            ksort($sums);
            $preview['sums'] = $sums;
        }

        // Zahlungen, die nach dem Export storniert wurden
        $cancelled = RentPayment::onlyTrashed()
            ->whereIn('id', DB::table('datev_export_rent_items')->select('rent_payment_id'))
            ->with(['lease.unit', 'lease.tenant'])->orderByDesc('deleted_at')->limit(30)->get();

        $accounts = DatevRentAccounts::all();
        $missing = [];
        foreach (array_keys(DatevRentAccounts::GROUPS) as $g) {
            if (trim((string) ($accounts[$g.':miete']['account'] ?? '')) === '') {
                $missing[] = DatevRentAccounts::GROUPS[$g];
            }
        }

        return view('datev.rent', [
            'config' => $config,
            'counters' => DatevRentAccounts::counterAccounts(),
            'missing' => $missing,
            'from' => $from,
            'to' => $to,
            'ownerId' => $ownerId,
            'again' => $again,
            'owners' => Owner::query()->orderBy('name')->get(['id', 'name']),
            'inputErrors' => $errors,
            'preview' => $preview,
            'exports' => DatevExport::query()->where('kind', 'mieten')->with(['creator', 'owner'])->orderByDesc('id')->limit(20)->get(),
            'cancelled' => $cancelled,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'von' => ['required', 'date_format:Y-m-d'],
            'bis' => ['required', 'date_format:Y-m-d'],
        ], ['von.*' => 'Das Datum „von“ ist ungültig.', 'bis.*' => 'Das Datum „bis“ ist ungültig.']);

        $from = $this->date($request->input('von'));
        $to = $this->date($request->input('bis'));
        $ownerId = (int) $request->input('eigentuemer') ?: null;
        $again = $request->boolean('nochmal');
        $back = array_filter(['von' => $request->input('von'), 'bis' => $request->input('bis'), 'eigentuemer' => $ownerId, 'nochmal' => $again ? 1 : null, 'vorschau' => 1]);

        $config = DatevConfig::load();
        $errors = $this->periodErrors($from, $to, $config);
        if ($errors !== []) {
            return redirect()->route('datev.rent', $back)->withErrors($errors);
        }

        $plan = DatevRentService::plan($from, $to, $ownerId, $again);
        if ($plan['problems'] !== []) {
            return redirect()->route('datev.rent', $back)->withErrors($plan['problems']);
        }
        if ($plan['skipped'] !== []) {
            return redirect()->route('datev.rent', $back)->withErrors(['Bei '.count($plan['skipped']).' Zahlung(en) fehlt noch ein Erlöskonto. Bitte zuerst die Erlöskonten vervollständigen, damit keine Zahlung fehlt.']);
        }
        if ($plan['bookings'] === []) {
            return redirect()->route('datev.rent', $back)->withErrors(['Für diesen Zeitraum gibt es keine Mietzahlungen, die exportiert werden können.']);
        }

        try {
            $export = DatevRentService::create($plan, $from, $to, $ownerId, $this->me());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->route('datev.rent', $back)->withErrors([$e->getMessage()]);
        }

        return redirect()->route('datev.rent')->with('status', 'Die Datei wurde erstellt: '.$export->entry_count.' Buchungen aus '.count($plan['payments']).' Zahlungen. Du findest sie unten unter „Bisherige Exporte“.');
    }

    public function accounts()
    {
        return view('datev.rent-accounts', [
            'config' => DatevConfig::load(),
            'accounts' => DatevRentAccounts::all(),
            'counters' => DatevRentAccounts::counterAccounts(),
        ]);
    }

    public function saveAccounts(Request $request)
    {
        $config = DatevConfig::load();
        $len = $config['account_length'];
        $accIn = (array) $request->input('account', []);
        $buIn = (array) $request->input('bu', []);
        $errors = [];
        $rows = [];

        foreach (DatevRentAccounts::GROUPS as $g => $gLabel) {
            foreach (DatevRentAccounts::PARTS as $p => $pLabel) {
                $key = $g.':'.$p;
                $a = trim((string) ($accIn[$key] ?? ''));
                $b = trim((string) ($buIn[$key] ?? ''));
                if ($a !== '' && ! DatevConfig::validAccount($a, $len)) {
                    $errors[] = $gLabel.', '.$pLabel.': Das Konto braucht genau '.$len.' Ziffern.';
                }
                if ($b !== '' && preg_match('/^\d{1,4}$/', $b) !== 1) {
                    $errors[] = $gLabel.', '.$pLabel.': Der Steuerschlüssel hat 1 bis 4 Ziffern.';
                }
                $rows[$key] = ['account' => $a, 'bu_key' => $b];
            }
        }

        $bank = trim((string) $request->input('bank_account'));
        $cash = trim((string) $request->input('cash_account'));
        if ($bank !== '' && ! DatevConfig::validCounterAccount($bank, $len)) {
            $errors[] = 'Das Bankkonto braucht '.$len.' Ziffern.';
        }
        if ($cash !== '' && ! DatevConfig::validCounterAccount($cash, $len)) {
            $errors[] = 'Das Kassenkonto braucht '.$len.' Ziffern.';
        }
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $changed = DatevRentAccounts::save($rows);

        $old = DatevRentAccounts::counterAccounts();
        if ($old['bank'] !== $bank || $old['cash'] !== $cash) {
            Settings::putMany(['datev_rent_bank_account' => $bank, 'datev_rent_cash_account' => $cash]);
            Audit::log('datev.rent_accounts', 'Gegenkonten für Mieten geändert', null, $old, ['bank' => $bank, 'cash' => $cash]);
            $changed++;
        }

        return redirect()->route('datev.rent.accounts')->with('status', $changed === 0 ? 'Es gab keine Änderungen.' : 'Die Konten wurden gespeichert.');
    }

    private function date(mixed $v): ?DateTimeImmutable
    {
        $v = (string) $v;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);

        return ($d !== false && $d->format('Y-m-d') === $v) ? $d : null;
    }

    private function periodErrors(?DateTimeImmutable $from, ?DateTimeImmutable $to, array $config): array
    {
        if ($from === null || $to === null) {
            return ['Der Zeitraum ist ungültig.'];
        }
        if ($from > $to) {
            return ['Das Ende des Zeitraums liegt vor dem Beginn.'];
        }
        if ($config['fy_month'] >= 1 && $config['fy_month'] <= 12 && $to > DatevConfig::fiscalYearEnd(DatevConfig::fiscalYearStart($from, $config['fy_month']))) {
            return ['Der Zeitraum geht über einen Jahreswechsel hinweg. Bitte je Wirtschaftsjahr getrennt exportieren.'];
        }

        return [];
    }
}
