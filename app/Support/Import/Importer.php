<?php

namespace App\Support\Import;

use App\Models\Lease;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Owner;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use App\Support\Stammdaten;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Prüft und übernimmt die Daten aus der Excel-Vorlage (Paket 15).
 * Erst wird alles geprüft (plan), eingetragen wird nur ohne Fehler (run), und dann alles oder nichts.
 */
class Importer
{
    private array $errors = [];

    private array $warnings = [];

    /** @var array<string, int> Name (klein) => Owner-ID */
    private array $owners = [];

    public function __construct(private User $user)
    {
        foreach (Owner::visibleTo($user)->get(['owners.id', 'owners.name']) as $o) {
            $this->owners[mb_strtolower(trim($o->name))] = (int) $o->id;
        }
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $sheets  aus XlsxReader
     * @return array{errors: array, warnings: array, counts: array, plan: array}
     */
    public function plan(array $sheets): array
    {
        $data = [];
        foreach (Vorlage::SHEETS as $name => $cols) {
            $data[$name] = $this->rows($sheets, $name, $cols);
        }
        $plan = ['objekte' => [], 'einheiten' => [], 'mieter' => [], 'vertraege' => [], 'zaehler' => []];

        // Objekte
        $propRefs = [];   // Kennung (klein) => ['new' => idx] | ['id' => id]
        foreach ($data['Objekte'] as $rowNo => $r) {
            $e = fn ($m) => $this->err('Objekte', $rowNo, $m);
            $key = mb_strtolower($r['kennung']);
            if ($r['kennung'] === '') {
                $e('Kennung fehlt.');

                continue;
            }
            if (isset($propRefs[$key])) {
                $e('Die Kennung „'.$r['kennung'].'“ kommt mehrfach vor.');

                continue;
            }
            $ownerId = $this->owner($r['eigentuemer'], fn ($m) => $e($m));
            if ($r['name'] === '') {
                $e('Objektname fehlt.');
            }
            $heating = $this->choice($r['heizung'], Stammdaten::HEATING, fn ($m) => $e('Heizung: '.$m));
            $water = $this->choice($r['warmwasser'], Stammdaten::HOT_WATER, fn ($m) => $e('Warmwasser: '.$m));
            if ($ownerId === null || $r['name'] === '') {
                continue;
            }
            $existing = Property::query()->where('owner_id', $ownerId)->whereRaw('lower(name) = ?', [mb_strtolower($r['name'])])->first();
            if ($existing) {
                $propRefs[$key] = ['id' => (int) $existing->id, 'owner' => $ownerId];
                $this->warn('Objekte', $rowNo, 'Objekt „'.$r['name'].'“ ist schon im CRM und wird nicht neu angelegt.');

                continue;
            }
            $plan['objekte'][] = ['row' => $rowNo, 'owner_id' => $ownerId, 'name' => mb_substr($r['name'], 0, 190), 'street' => $r['strasse'] ?: null, 'zip' => $r['plz'] ?: null, 'city' => $r['ort'] ?: null, 'heating_type' => $heating, 'hot_water_type' => $water, 'notes' => $r['notizen'] ?: null];
            $propRefs[$key] = ['new' => count($plan['objekte']) - 1, 'owner' => $ownerId];
        }

        // Einheiten
        $unitRefs = [];  // "propkey|label" => ['new' => idx] | ['id' => id]
        $usage = ['wohnen' => 'wohnen', 'wohnung' => 'wohnen', 'gewerbe mit ust' => 'gewerbe_option', 'gewerbe mit ust-option' => 'gewerbe_option', 'gewerbe ohne ust' => 'gewerbe_ohne_option', 'gewerbe' => 'gewerbe_ohne_option', 'montage' => 'montage', 'montage-wohnung' => 'montage'];
        foreach ($data['Einheiten'] as $rowNo => $r) {
            $e = fn ($m) => $this->err('Einheiten', $rowNo, $m);
            $prop = $this->property($r['objekt'], $propRefs, fn ($m) => $e($m));
            if ($r['bezeichnung'] === '') {
                $e('Bezeichnung fehlt.');
            }
            $u = $usage[mb_strtolower($r['nutzung'])] ?? (isset(Stammdaten::USAGE[$r['nutzung']]) ? $r['nutzung'] : null);
            if ($u === null) {
                $e('Nutzung „'.$r['nutzung'].'“ ist unbekannt. Erlaubt: Wohnen, Gewerbe mit USt, Gewerbe ohne USt, Montage.');
            }
            $area = $this->number($r['flaeche'], fn ($m) => $e('Fläche: '.$m));
            $rooms = $this->number($r['zimmer'], fn ($m) => $e('Zimmer: '.$m));
            if ($prop === null || $r['bezeichnung'] === '' || $u === null) {
                continue;
            }
            $ukey = $prop['key'].'|'.mb_strtolower($r['bezeichnung']);
            if (isset($unitRefs[$ukey])) {
                $e('Die Einheit „'.$r['bezeichnung'].'“ steht mehrfach beim selben Objekt.');

                continue;
            }
            if (isset($prop['id'])) {
                $ex = Unit::query()->where('property_id', $prop['id'])->whereRaw('lower(label) = ?', [mb_strtolower($r['bezeichnung'])])->first();
                if ($ex) {
                    $unitRefs[$ukey] = ['id' => (int) $ex->id, 'usage' => $ex->usage_type, 'owner' => $prop['owner']];
                    $this->warn('Einheiten', $rowNo, 'Einheit „'.$r['bezeichnung'].'“ ist schon im CRM und wird nicht neu angelegt.');

                    continue;
                }
            }
            $plan['einheiten'][] = ['row' => $rowNo, 'prop' => $prop, 'label' => mb_substr($r['bezeichnung'], 0, 100), 'floor' => $r['etage'] ?: null, 'usage_type' => $u, 'area_sqm' => $area, 'rooms' => $rooms !== null ? number_format(round((float) $rooms * 2) / 2, 1, '.', '') : null, 'equipment' => $r['ausstattung'] ?: null];
            $unitRefs[$ukey] = ['new' => count($plan['einheiten']) - 1, 'usage' => $u, 'owner' => $prop['owner']];
        }

        // Mieter
        $tenantRefs = [];
        foreach ($data['Mieter'] as $rowNo => $r) {
            $e = fn ($m) => $this->err('Mieter', $rowNo, $m);
            $key = mb_strtolower($r['kennung']);
            if ($r['kennung'] === '') {
                $e('Kennung fehlt.');

                continue;
            }
            if (isset($tenantRefs[$key])) {
                $e('Die Kennung „'.$r['kennung'].'“ kommt mehrfach vor.');

                continue;
            }
            $ownerId = $this->owner($r['eigentuemer'], fn ($m) => $e($m));
            $type = in_array(mb_strtolower($r['art']), ['firma', 'unternehmen', 'gewerbe'], true) ? 'firma' : 'person';
            if ($r['art'] !== '' && ! in_array(mb_strtolower($r['art']), ['firma', 'unternehmen', 'gewerbe', 'person', 'privat', 'privatperson'], true)) {
                $e('Art „'.$r['art'].'“ ist unbekannt (Person oder Firma).');
            }
            if ($type === 'firma' && $r['firma'] === '') {
                $e('Bei einer Firma bitte den Firmennamen angeben.');
            }
            if ($type === 'person' && $r['nachname'] === '') {
                $e('Nachname fehlt.');
            }
            if ($r['email'] !== '' && ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $e('E-Mail-Adresse „'.$r['email'].'“ ist ungültig.');
            }
            $row = ['type' => $type, 'first_name' => $r['vorname'] ?: null, 'last_name' => $r['nachname'] ?: null, 'company_name' => $r['firma'] ?: null];
            $display = Tenant::composeName($row);
            if ($ownerId === null || $display === '') {
                continue;
            }
            $ex = Tenant::query()->where('owner_id', $ownerId)->whereRaw('lower(display_name) = ?', [mb_strtolower($display)])->first();
            if ($ex) {
                $tenantRefs[$key] = ['id' => (int) $ex->id, 'owner' => $ownerId];
                $this->warn('Mieter', $rowNo, 'Mieter „'.$display.'“ ist schon im CRM und wird nicht neu angelegt.');

                continue;
            }
            $plan['mieter'][] = ['row' => $rowNo, 'owner_id' => $ownerId, 'display_name' => mb_substr($display, 0, 190)] + $row + [
                'street' => $r['strasse'] ?: null, 'zip' => $r['plz'] ?: null, 'city' => $r['ort'] ?: null, 'email' => $r['email'] ?: null,
                'phone' => $r['telefon'] ?: null, 'vat_id' => $r['ustid'] ?: null, 'notes' => $r['notizen'] ?: null,
            ];
            $tenantRefs[$key] = ['new' => count($plan['mieter']) - 1, 'owner' => $ownerId];
        }

        // Mietverträge
        $types = ['wohnraum' => 'wohnen', 'wohnen' => 'wohnen', 'gewerbe' => 'gewerbe', 'montage' => 'montage', 'montage-wohnung' => 'montage'];
        $periods = [];   // unit key => [[start, end]]
        foreach ($data['Mietverträge'] as $rowNo => $r) {
            $e = fn ($m) => $this->err('Mietverträge', $rowNo, $m);
            $prop = $this->property($r['objekt'], $propRefs, fn ($m) => $e($m));
            $unit = null;
            if ($prop !== null) {
                $ukey = $prop['key'].'|'.mb_strtolower($r['einheit']);
                $unit = $unitRefs[$ukey] ?? null;
                if ($unit === null && isset($prop['id'])) {
                    $ex = Unit::query()->where('property_id', $prop['id'])->whereRaw('lower(label) = ?', [mb_strtolower($r['einheit'])])->first();
                    $unit = $ex ? ['id' => (int) $ex->id, 'usage' => $ex->usage_type, 'owner' => $prop['owner']] : null;
                }
                if ($unit === null) {
                    $e('Einheit „'.$r['einheit'].'“ gibt es bei diesem Objekt nicht (weder im Blatt „Einheiten“ noch im CRM).');
                }
            }
            $tenant = $this->tenant($r['mieter'], $tenantRefs, $prop['owner'] ?? null, fn ($m) => $e($m));
            if ($tenant !== null && $prop !== null && $tenant['owner'] !== $prop['owner']) {
                $e('Mieter und Objekt gehören zu verschiedenen Eigentümern.');
            }
            $start = $this->date($r['beginn'], fn ($m) => $e('Beginn: '.$m));
            if ($r['beginn'] === '') {
                $e('Beginn fehlt.');
            }
            $end = $this->date($r['ende'], fn ($m) => $e('Ende: '.$m));
            if ($start && $end && $end < $start) {
                $e('Das Ende liegt vor dem Beginn.');
            }
            $sollAb = $this->date($r['mietbeginn_crm'], fn ($m) => $e('Soll ab: '.$m));
            if ($sollAb && $start && $sollAb < $start) {
                $sollAb = null;
            }
            $comp = [];
            foreach (['rent_cold' => 'kaltmiete', 'prepay_operating' => 'nebenkosten', 'prepay_heating' => 'heizkosten', 'parking' => 'stellplatz', 'garage' => 'garage', 'furniture' => 'moebel'] as $col => $f) {
                $comp[$col] = $this->money($r[$f], fn ($m) => $e(Vorlage::SHEETS['Mietverträge'][$f][0].': '.$m)) ?? '0.00';
            }
            if (array_sum(array_map('floatval', $comp)) <= 0) {
                $e('Kaltmiete fehlt (bei reinen Stellplatz- oder Garagenverträgen bitte den Betrag bei Stellplatz bzw. Garage eintragen).');
            }
            $vat = $r['ust'] === '' ? '0' : str_replace([',', '%', ' '], ['.', '', ''], $r['ust']);
            if (! in_array((string) (float) $vat, ['0', '7', '19'], true)) {
                $e('USt muss 0, 7 oder 19 sein.');
            }
            $due = $r['faellig'] === '' ? 3 : (int) $r['faellig'];
            if ($due < 1 || $due > 28) {
                $e('Fällig am Tag muss zwischen 1 und 28 liegen.');
            }
            $deposit = $this->money($r['kaution'], fn ($m) => $e('Kaution: '.$m));
            $adj = match (mb_strtolower($r['mietart'])) { '', 'fest' => 'fest', 'staffel', 'staffelmiete' => 'staffel', 'index', 'indexmiete' => 'index', default => null };
            if ($adj === null) {
                $e('Mietart muss fest, Staffel oder Index sein.');
            }
            $indexMonth = null;
            $indexValue = null;
            if ($adj === 'index') {
                if (preg_match('/^(\d{1,2})[.\/](\d{4})$/', $r['index_monat'], $m)) {
                    $indexMonth = $m[2].'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT).'-01';
                } else {
                    $d = $this->date($r['index_monat'], fn () => null);
                    $indexMonth = $d ? substr($d, 0, 8).'01' : null;
                }
                $indexValue = $this->money($r['index_wert'], fn ($m) => $e('Index-Basiswert: '.$m));
                if ($indexMonth === null || $indexValue === null) {
                    $e('Bei Indexmiete bitte Index-Basismonat (MM.JJJJ) und Index-Basiswert angeben.');
                }
            }
            $type = $r['vertragsart'] === '' ? match ($unit['usage'] ?? 'wohnen') { 'montage' => 'montage', 'gewerbe_option', 'gewerbe_ohne_option' => 'gewerbe', default => 'wohnen' } : ($types[mb_strtolower($r['vertragsart'])] ?? null);
            if ($type === null) {
                $e('Vertragsart muss Wohnraum, Gewerbe oder Montage sein.');
            }
            if ($unit === null || $tenant === null || $start === null || $type === null || $adj === null) {
                continue;
            }
            // Überschneidung im CRM und in der Datei
            $uk = isset($unit['id']) ? 'id'.$unit['id'] : 'new'.$unit['new'];
            foreach ($periods[$uk] ?? [] as [$s2, $e2]) {
                if (($end === null || $s2 <= $end) && ($e2 === null || $e2 >= $start)) {
                    $e('Für diese Einheit gibt es in der Datei schon einen Vertrag im selben Zeitraum.');

                    continue 2;
                }
            }
            if (isset($unit['id'])) {
                $ov = Lease::query()->where('unit_id', $unit['id'])
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
                    ->when($end, fn ($q) => $q->where('start_date', '<=', $end))->with('tenant')->first();
                if ($ov) {
                    $this->warn('Mietverträge', $rowNo, 'Für diese Einheit gibt es im CRM schon einen Vertrag im selben Zeitraum ('.($ov->tenant?->display_name ?? 'Mieter').'). Die Zeile wird übersprungen.');

                    continue;
                }
            }
            $periods[$uk][] = [$start, $end];
            $plan['vertraege'][] = ['row' => $rowNo, 'unit' => $unit, 'tenant' => $tenant, 'owner_id' => $unit['owner'], 'lease_type' => $type, 'start_date' => $start, 'end_date' => $end,
                'due_day' => $due, 'vat_rate' => (string) (float) $vat, 'adjustment_type' => $adj, 'index_base_month' => $indexMonth, 'index_base_value' => $indexValue,
                'deposit_amount' => $deposit, 'note' => $r['notiz'] ?: null, 'rent' => $comp, 'soll_ab' => $sollAb];
        }

        // Zähler
        $kinds = ['kaltwasser' => 'wasser', 'wasser' => 'wasser', 'warmwasser' => 'warmwasser', 'wärmemenge' => 'waerme', 'wärmemengenzähler' => 'waerme', 'waerme' => 'waerme', 'heizkostenverteiler' => 'hkv', 'hkv' => 'hkv'];
        foreach ($data['Zähler'] as $rowNo => $r) {
            $e = fn ($m) => $this->err('Zähler', $rowNo, $m);
            $prop = $this->property($r['objekt'], $propRefs, fn ($m) => $e($m));
            $unit = null;
            if ($prop !== null && $r['einheit'] !== '') {
                $unit = $unitRefs[$prop['key'].'|'.mb_strtolower($r['einheit'])] ?? null;
                if ($unit === null && isset($prop['id'])) {
                    $ex = Unit::query()->where('property_id', $prop['id'])->whereRaw('lower(label) = ?', [mb_strtolower($r['einheit'])])->first();
                    $unit = $ex ? ['id' => (int) $ex->id] : null;
                }
                if ($unit === null) {
                    $e('Einheit „'.$r['einheit'].'“ gibt es bei diesem Objekt nicht.');
                }
            }
            $kind = $kinds[mb_strtolower($r['art'])] ?? null;
            if ($kind === null) {
                $e('Art „'.$r['art'].'“ ist unbekannt (Kaltwasser, Warmwasser, Wärmemenge, Heizkostenverteiler).');
            }
            $factor = $this->number($r['faktor'], fn ($m) => $e('Faktor: '.$m));
            $date = $this->date($r['datum'], fn ($m) => $e('Ablesedatum: '.$m));
            $value = $this->number($r['stand'], fn ($m) => $e('Zählerstand: '.$m));
            if (($date === null) !== ($value === null)) {
                $e('Für einen Anfangsstand bitte Ablesedatum und Zählerstand angeben.');
            }
            if ($prop === null || $kind === null || ($r['einheit'] !== '' && $unit === null)) {
                continue;
            }
            if ($r['nummer'] !== '' && isset($prop['id']) && Meter::query()->where('property_id', $prop['id'])->where('number', $r['nummer'])->exists()) {
                $this->warn('Zähler', $rowNo, 'Zähler Nr. '.$r['nummer'].' ist schon im CRM und wird nicht neu angelegt.');

                continue;
            }
            $plan['zaehler'][] = ['row' => $rowNo, 'prop' => $prop, 'unit' => $unit, 'kind' => $kind, 'label' => $r['bezeichnung'] ?: null, 'number' => $r['nummer'] ?: null, 'factor' => $factor ?? '1', 'date' => $date, 'value' => $value];
        }

        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'counts' => array_map('count', $plan),
            'plan' => $plan,
        ];
    }

    /** Alles anlegen (in einer Transaktion). @return array<string, int> */
    public function run(array $plan): array
    {
        $leases = [];
        DB::transaction(function () use ($plan, &$leases) {
            $props = [];
            foreach ($plan['objekte'] as $i => $p) {
                $props[$i] = Property::create(array_diff_key($p, ['row' => 1]))->id;
            }
            $pid = fn (array $ref) => $ref['id'] ?? $props[$ref['new']];
            $units = [];
            foreach ($plan['einheiten'] as $i => $u) {
                $units[$i] = Unit::create(['property_id' => $pid($u['prop'])] + array_diff_key($u, ['row' => 1, 'prop' => 1]))->id;
            }
            $uid = fn (array $ref) => $ref['id'] ?? $units[$ref['new']];
            $tenants = [];
            foreach ($plan['mieter'] as $i => $t) {
                $tenants[$i] = Tenant::create(array_diff_key($t, ['row' => 1]))->id;
            }
            $tid = fn (array $ref) => $ref['id'] ?? $tenants[$ref['new']];
            foreach ($plan['vertraege'] as $v) {
                $lease = Lease::create([
                    'owner_id' => $v['owner_id'], 'unit_id' => $uid($v['unit']), 'tenant_id' => $tid($v['tenant']), 'lease_type' => $v['lease_type'],
                    'start_date' => $v['start_date'], 'end_date' => $v['end_date'], 'due_day' => $v['due_day'], 'payment_term_days' => null,
                    'vat_rate' => $v['vat_rate'], 'adjustment_type' => $v['adjustment_type'], 'index_base_value' => $v['index_base_value'],
                    'index_base_month' => $v['index_base_month'], 'deposit_amount' => $v['deposit_amount'], 'note' => $v['note'], 'created_by' => $this->user->id,
                ]);
                $lease->rents()->create($v['rent'] + ['valid_from' => $v['start_date'], 'reason' => 'beginn', 'note' => 'Import', 'created_by' => $this->user->id]);
                $leases[] = [$lease, $v['soll_ab']];
            }
            foreach ($plan['zaehler'] as $z) {
                $m = Meter::create(['property_id' => $pid($z['prop']), 'unit_id' => $z['unit'] ? $uid($z['unit']) : null, 'kind' => $z['kind'], 'label' => $z['label'], 'number' => $z['number'], 'factor' => $z['factor'], 'active' => true]);
                if ($z['date'] !== null) {
                    MeterReading::create(['meter_id' => $m->id, 'read_on' => $z['date'], 'value' => $z['value'], 'note' => 'Import', 'created_by' => $this->user->id]);
                }
            }
        });

        // Soll-Mieten berechnen; vor dem Stichtag „Soll ab“ gilt alles als bezahlt
        foreach ($leases as [$lease, $sollAb]) {
            RentLedger::sync($lease);
            if ($sollAb !== null) {
                $sum = (float) RentCharge::query()->where('lease_id', $lease->id)->where('period', '<', $sollAb)->sum('total');
                if ($sum > 0) {
                    RentPayment::create([
                        'lease_id' => $lease->id, 'owner_id' => $lease->owner_id, 'paid_on' => (new DateTimeImmutable($sollAb))->modify('-1 day')->format('Y-m-d'),
                        'amount' => number_format($sum, 2, '.', ''), 'method' => 'sonstiges', 'source' => 'import',
                        'note' => 'Übernahme beim Import: Mieten vor dem '.date('d.m.Y', strtotime($sollAb)).' gelten als bezahlt', 'created_by' => $this->user->id,
                    ]);
                    RentLedger::sync($lease);
                }
            }
        }

        $counts = array_map('count', $plan);
        Audit::log('import.done', 'Excel-Import: '.$counts['objekte'].' Objekte, '.$counts['einheiten'].' Einheiten, '.$counts['mieter'].' Mieter, '.$counts['vertraege'].' Mietverträge, '.$counts['zaehler'].' Zähler');

        return $counts;
    }

    /* ---------- Hilfen ---------- */

    private function rows(array $sheets, string $name, array $cols): array
    {
        $rows = $sheets[$name] ?? null;
        if ($rows === null) {
            foreach ($sheets as $n => $r) {
                if (mb_strtolower(trim($n)) === mb_strtolower($name)) {
                    $rows = $r;
                }
            }
        }
        if (! $rows) {
            return [];
        }
        $headerRowNo = array_key_first($rows);
        $map = [];
        $norm = fn ($s) => preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower(str_replace('*', '', (string) $s)));
        foreach ($rows[$headerRowNo] as $ci => $h) {
            foreach ($cols as $key => [$label]) {
                if ($norm($h) === $norm($label)) {
                    $map[$key] = $ci;
                }
            }
        }
        $missing = array_diff(array_keys(array_filter($cols, fn ($c) => $c[1])), array_keys($map));
        if ($missing !== [] && count($rows) > 1) {
            $this->err($name, $headerRowNo, 'Spalte(n) fehlen in der Kopfzeile: '.implode(', ', array_map(fn ($k) => $cols[$k][0], $missing)).'. Bitte die Vorlage aus dem CRM verwenden.');
        }
        $out = [];
        foreach ($rows as $no => $row) {
            if ($no === $headerRowNo) {
                continue;
            }
            $r = [];
            foreach ($cols as $key => $c) {
                $r[$key] = isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            }
            if (implode('', $r) !== '') {
                $out[$no] = $r;
            }
        }

        return $out;
    }

    private function err(string $sheet, int $row, string $msg): void
    {
        $this->errors[] = ['sheet' => $sheet, 'row' => $row, 'message' => $msg];
    }

    private function warn(string $sheet, int $row, string $msg): void
    {
        $this->warnings[] = ['sheet' => $sheet, 'row' => $row, 'message' => $msg];
    }

    private function owner(string $name, callable $e): ?int
    {
        if ($name === '') {
            $e('Eigentümer fehlt.');

            return null;
        }
        $id = $this->owners[mb_strtolower($name)] ?? null;
        if ($id === null) {
            $e('Eigentümer „'.$name.'“ gibt es im CRM nicht (oder er ist für dich nicht freigegeben). Bitte genau wie im CRM schreiben.');
        }

        return $id;
    }

    private function property(string $ref, array $refs, callable $e): ?array
    {
        if ($ref === '') {
            $e('Objekt fehlt.');

            return null;
        }
        $key = mb_strtolower($ref);
        if (isset($refs[$key])) {
            return $refs[$key] + ['key' => $key];
        }
        $ps = Property::query()->whereIn('owner_id', array_values($this->owners))->whereRaw('lower(name) = ?', [$key])->get();
        if ($ps->count() === 1) {
            return ['id' => (int) $ps[0]->id, 'owner' => (int) $ps[0]->owner_id, 'key' => 'p'.$ps[0]->id];
        }
        $e($ps->count() > 1 ? 'Objektname „'.$ref.'“ ist nicht eindeutig. Bitte im Blatt „Objekte“ mit Kennung anlegen.' : 'Objekt „'.$ref.'“ gibt es weder im Blatt „Objekte“ noch im CRM.');

        return null;
    }

    private function tenant(string $ref, array $refs, ?int $ownerId, callable $e): ?array
    {
        if ($ref === '') {
            $e('Mieter fehlt.');

            return null;
        }
        if (isset($refs[mb_strtolower($ref)])) {
            return $refs[mb_strtolower($ref)];
        }
        $ts = Tenant::query()->whereIn('owner_id', $ownerId ? [$ownerId] : array_values($this->owners))->whereRaw('lower(display_name) = ?', [mb_strtolower($ref)])->get();
        if ($ts->count() === 1) {
            return ['id' => (int) $ts[0]->id, 'owner' => (int) $ts[0]->owner_id];
        }
        $e($ts->count() > 1 ? 'Mieter „'.$ref.'“ ist nicht eindeutig.' : 'Mieter „'.$ref.'“ gibt es weder im Blatt „Mieter“ noch im CRM.');

        return null;
    }

    private function choice(string $value, array $options, callable $e): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach ($options as $k => $label) {
            if (mb_strtolower($value) === mb_strtolower($label) || mb_strtolower($value) === mb_strtolower($k)) {
                return $k;
            }
        }
        $e('„'.$value.'“ ist unbekannt. Erlaubt: '.implode(', ', $options).'.');

        return null;
    }

    /** Datum aus TT.MM.JJJJ, JJJJ-MM-TT oder Excel-Seriennummer. */
    private function date(string $v, callable $e): ?string
    {
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{4,5}(\.0+)?$/', $v)) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $v.' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/', $v, $m)) {
            $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        $e('„'.$v.'“ ist kein gültiges Datum (TT.MM.JJJJ).');

        return null;
    }

    private function money(string $v, callable $e): ?string
    {
        if ($v === '') {
            return null;
        }
        $v = str_replace(['€', ' '], '', $v);
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);   // 1.800 = 1800
        }
        $p = is_numeric($v) && ! str_contains($v, ',') ? number_format((float) $v, 2, '.', '') : Money::parse($v);
        if ($p === null || (float) $p < 0) {
            $e('„'.$v.'“ ist kein gültiger Betrag.');

            return null;
        }

        return $p;
    }

    private function number(string $v, callable $e): ?string
    {
        if ($v === '') {
            return null;
        }
        $n = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : $v;
        if (! is_numeric($n) || (float) $n < 0) {
            $e('„'.$v.'“ ist keine gültige Zahl.');

            return null;
        }

        return (string) round((float) $n, 3);
    }
}
