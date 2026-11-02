<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Support\Audit;
use App\Models\RentCharge;
use App\Support\Bescheinigung\Bescheinigung;
use App\Support\Bescheinigung\Kuendigung;
use App\Support\Rent\Mieten;
use App\Support\Rent\RentLedger;
use Illuminate\Http\Request;

/** Schreiben an Mieter: Wohnungsgeberbestätigung, Mietschuldenfreiheit (Paket 44), Abmahnung, Kündigung (Paket 45), Kündigungsbestätigung, Terminankündigung (Paket 46). */
class BescheinigungController extends Controller
{
    use ScopesOwners;

    /** Einstieg aus dem Menü: Mietvertrag auswählen (laufende, künftige und im letzten Jahr beendete). */
    public function choose(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $since = date('Y-m-d', strtotime('-12 months'));
        $leases = Lease::visibleTo($this->me())
            ->with(['unit.property', 'tenant', 'owner'])
            ->where(fn ($w) => $w->whereNull('leases.end_date')->orWhere('leases.end_date', '>=', $since))
            ->get()
            ->filter(function ($l) use ($q) {
                if ($q === '') {
                    return true;
                }
                $hay = mb_strtolower(implode(' ', [$l->tenant?->display_name, $l->tenant?->first_name, $l->tenant?->last_name, $l->tenant?->company_name, $l->unit?->label, $l->unit?->property?->name, $l->unit?->property?->street]));

                return str_contains($hay, mb_strtolower($q));
            })
            ->sortBy(fn ($l) => [$l->unit?->property?->name, $l->unit?->label, $l->start_date->format('Y-m-d')])
            ->values();

        return view('bescheinigung.choose', ['leases' => $leases, 'q' => $q, 'today' => date('Y-m-d')]);
    }

    public function index(Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        RentLedger::syncAllIfStale();
        $today = date('Y-m-d');

        return view('bescheinigung.index', Bescheinigung::basis($lease) + [
            'today' => $today,
            'personen' => Bescheinigung::personenVorschlag($lease),
            'offen' => Bescheinigung::offenBis($lease, $today),
            'erfasstAb' => Mieten::start(),
            'vorgaenge' => Bescheinigung::VORGAENGE,
            'abmahnGruende' => Kuendigung::ABMAHNUNG_GRUENDE,
            'arten' => Kuendigung::ARTEN,
            'gruende' => Kuendigung::GRUENDE,
            'gewerbe' => $lease->lease_type === 'gewerbe',
            'frist' => $lease->start_date ? Kuendigung::ordentlichesEnde((string) $lease->lease_type, $lease->start_date->format('Y-m-d'), $today) : null,
            'verzug' => $this->verzug($lease, $today),
            'mieterFrist' => Kuendigung::mieterEnde((string) $lease->lease_type, $today),
            'terminArten' => Kuendigung::TERMIN_ARTEN,
            'hausAnzahl' => $this->hausLeases($lease, $today)->count(),
        ]);
    }

    /** Laufende Mietverträge im selben Haus (für Ankündigungen an alle). */
    private function hausLeases(Lease $lease, string $date)
    {
        $propertyId = $lease->unit?->property_id;
        if (! $propertyId) {
            return collect([$lease]);
        }

        return Lease::visibleTo($this->me())->activeOn($date)
            ->whereIn('leases.unit_id', \App\Models\Unit::query()->where('property_id', $propertyId)->select('id'))
            ->with(['unit.property', 'tenant', 'owner'])->get()
            ->sortBy(fn ($l) => $l->unit?->label)->values();
    }

    /** Bestätigung der Kündigung durch den Mieter; auf Wunsch wird das Vertragsende eingetragen. */
    public function kuendigungsbestaetigung(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'eingang' => ['required', 'date_format:Y-m-d'],
            'gewuenscht' => ['nullable', 'date_format:Y-m-d'],
            'uebergabe_tag' => ['nullable', 'date_format:Y-m-d'],
            'uebergabe_zeit' => ['nullable', 'string', 'max:20'],
            'briefdatum' => ['required', 'date_format:Y-m-d'],
            'eintragen' => ['nullable', 'boolean'],
            'zusatz' => ['nullable', 'string', 'max:2000'],
        ], [
            'eingang.*' => 'Bitte angeben, wann die Kündigung des Mieters eingegangen ist.',
            'briefdatum.*' => 'Bitte das Datum des Schreibens angeben.',
        ]);
        $frist = Kuendigung::mieterEnde((string) $lease->lease_type, $data['eingang']);
        $gewuenscht = $data['gewuenscht'] ?? null;
        // Ein späterer Wunschtermin des Mieters gilt, ein früherer nicht
        $ende = $gewuenscht !== null && $gewuenscht > $frist['ende'] ? $gewuenscht : $frist['ende'];

        if ((bool) ($data['eintragen'] ?? false) && $lease->start_date && $ende >= $lease->start_date->format('Y-m-d')) {
            $old = $lease->end_date?->format('Y-m-d');
            if ($old !== $ende) {
                $lease->end_date = $ende;
                $lease->save();
                RentLedger::sync($lease->fresh());
                Audit::log('lease.ended', $lease, 'Vertragsende nach Kündigung des Mieters eingetragen: '.Bescheinigung::de($ende).($old ? ' (vorher '.Bescheinigung::de($old).')' : '').' – '.$lease->title());
            }
        }

        Audit::log('letter.termination_confirmed', $lease, 'Kündigungsbestätigung (Ende '.Bescheinigung::de($ende).') erstellt: '.$lease->title());

        return view('bescheinigung.kuendigungsbestaetigung', Bescheinigung::basis($lease->fresh()) + [
            'eingang' => $data['eingang'],
            'gewuenscht' => $gewuenscht,
            'endeNeu' => $ende,
            'frist' => $frist,
            'frueher' => $gewuenscht !== null && $gewuenscht < $frist['ende'],
            'uebergabeTag' => $data['uebergabe_tag'] ?? null,
            'uebergabeZeit' => trim((string) ($data['uebergabe_zeit'] ?? '')),
            'briefdatum' => $data['briefdatum'],
            'zusatz' => trim((string) ($data['zusatz'] ?? '')),
            'gewerbe' => $lease->lease_type === 'gewerbe',
        ]);
    }

    /** Ankündigung eines Termins in der Wohnung (Handwerker, Wartung, Ablesung, Besichtigung), auf Wunsch an alle Mieter im Haus. */
    public function termin(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'art' => ['required', 'in:'.implode(',', array_keys(Kuendigung::TERMIN_ARTEN))],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'tag' => ['required', 'date_format:Y-m-d'],
            'von' => ['nullable', 'date_format:H:i'],
            'bis' => ['nullable', 'date_format:H:i'],
            'wer' => ['nullable', 'string', 'max:190'],
            'kontakt' => ['nullable', 'string', 'max:190'],
            'melden_bis' => ['nullable', 'date_format:Y-m-d'],
            'briefdatum' => ['required', 'date_format:Y-m-d'],
            'alle' => ['nullable', 'boolean'],
        ], [
            'art.*' => 'Bitte den Anlass wählen.',
            'tag.*' => 'Bitte den Tag des Termins angeben.',
            'von.*' => 'Bitte die Uhrzeit als HH:MM angeben.',
            'bis.*' => 'Bitte die Uhrzeit als HH:MM angeben.',
            'briefdatum.*' => 'Bitte das Datum des Schreibens angeben.',
        ]);
        if ($data['art'] === 'sonstiges' && trim((string) ($data['beschreibung'] ?? '')) === '') {
            return redirect()->route('bescheinigung.index', $lease)->withErrors(['termin' => 'Bitte kurz beschreiben, worum es bei dem Termin geht.'])->withInput();
        }
        $leases = (bool) ($data['alle'] ?? false) ? $this->hausLeases($lease, $data['tag']) : collect([$lease]);
        if ($leases->isEmpty()) {
            $leases = collect([$lease]);
        }
        $briefe = $leases->map(fn ($l) => Bescheinigung::basis($l))->all();
        Audit::log('letter.appointment', $lease, 'Terminankündigung ('.Kuendigung::TERMIN_ARTEN[$data['art']].' am '.Bescheinigung::de($data['tag']).') für '.count($briefe).' Mietverhältnis(se) erstellt: '.$lease->title());

        return view('bescheinigung.termin', [
            'briefe' => $briefe,
            'art' => $data['art'],
            'artText' => Kuendigung::TERMIN_ARTEN[$data['art']],
            'beschreibung' => trim((string) ($data['beschreibung'] ?? '')),
            'tag' => $data['tag'],
            'von' => $data['von'] ?? null,
            'bis' => $data['bis'] ?? null,
            'wer' => trim((string) ($data['wer'] ?? '')),
            'kontakt' => trim((string) ($data['kontakt'] ?? '')),
            'meldenBis' => $data['melden_bis'] ?? null,
            'briefdatum' => $data['briefdatum'],
        ]);
    }

    /** Offene Monatsmieten bis heute und die Prüfung nach § 543 Abs. 2 Nr. 3 BGB. */
    private function verzug(Lease $lease, string $today): array
    {
        $rows = RentCharge::query()->where('lease_id', $lease->id)->where('kind', 'miete')->where('due_date', '<', $today)
            ->orderBy('due_date')->get();
        $open = [];
        foreach ($rows as $c) {
            $open[] = ['monat' => $c->period->format('m.Y'), 'faellig' => $c->due_date->format('Y-m-d'), 'offen' => round(max(0, (float) $c->total - (float) $c->paid_amount), 2)];
        }
        $monthly = Bescheinigung::miete($lease, $today)[2];
        $check = Kuendigung::zahlungsverzug(array_column($open, 'offen'), $monthly);

        return ['zeilen' => array_values(array_filter($open, fn ($r) => $r['offen'] > 0.004)), 'summe' => round(array_sum(array_column($open, 'offen')), 2), 'monatsmiete' => $monthly] + $check;
    }

    public function abmahnung(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'grund' => ['required', 'in:'.implode(',', array_keys(Kuendigung::ABMAHNUNG_GRUENDE))],
            'sachverhalt' => ['required', 'string', 'max:4000'],
            'aufforderung' => ['nullable', 'string', 'max:2000'],
            'frist' => ['nullable', 'date_format:Y-m-d'],
            'briefdatum' => ['required', 'date_format:Y-m-d'],
        ], [
            'grund.*' => 'Bitte den Grund der Abmahnung wählen.',
            'sachverhalt.*' => 'Bitte genau beschreiben, was vorgefallen ist (was, wann, wer).',
            'briefdatum.*' => 'Bitte das Datum des Schreibens angeben.',
        ]);
        $default = [
            'zahlung' => 'die Miete künftig pünktlich und vollständig zu zahlen, spätestens bis zum '.((int) $lease->due_day ?: 3).'. Tag eines jeden Monats',
            'ruhe' => 'die Ruhezeiten der Hausordnung künftig einzuhalten und jede vermeidbare Lärmbelästigung der Nachbarn zu unterlassen',
            'hausordnung' => 'die Hausordnung künftig einzuhalten',
            'untervermietung' => 'die Gebrauchsüberlassung an Dritte sofort zu beenden',
            'tiere' => 'das Tier aus der Wohnung zu entfernen',
            'gebrauch' => 'den vertragswidrigen Gebrauch sofort zu unterlassen',
            'sonstiges' => 'dieses Verhalten künftig zu unterlassen',
        ];
        $base = Bescheinigung::basis($lease);
        Audit::log('letter.warning', $lease, 'Abmahnung ('.Kuendigung::ABMAHNUNG_GRUENDE[$data['grund']].') erstellt: '.$lease->title());

        return view('bescheinigung.abmahnung', $base + [
            'grund' => $data['grund'],
            'grundText' => Kuendigung::ABMAHNUNG_GRUENDE[$data['grund']],
            'sachverhalt' => trim($data['sachverhalt']),
            'aufforderung' => trim((string) ($data['aufforderung'] ?? '')) ?: $default[$data['grund']],
            'frist' => $data['frist'] ?? null,
            'briefdatum' => $data['briefdatum'],
            'gewerbe' => $lease->lease_type === 'gewerbe',
        ]);
    }

    public function kuendigung(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $gewerbe = $lease->lease_type === 'gewerbe';
        $data = $request->validate([
            'art' => ['required', 'in:'.implode(',', array_keys(Kuendigung::ARTEN))],
            'grund' => ['nullable', 'in:'.implode(',', array_keys(Kuendigung::GRUENDE))],
            'begruendung' => ['nullable', 'string', 'max:5000'],
            'zugang' => ['required', 'date_format:Y-m-d'],
            'briefdatum' => ['required', 'date_format:Y-m-d'],
            'raeumung' => ['nullable', 'date_format:Y-m-d'],
            'hilfsweise' => ['nullable', 'boolean'],
        ], [
            'art.*' => 'Bitte die Art der Kündigung wählen.',
            'zugang.*' => 'Bitte angeben, wann das Schreiben dem Mieter voraussichtlich zugeht.',
            'briefdatum.*' => 'Bitte das Datum des Schreibens angeben.',
        ]);
        $begruendung = trim((string) ($data['begruendung'] ?? ''));
        $back = fn (string $msg) => redirect()->route('bescheinigung.index', $lease)->withErrors(['kuendigung' => $msg])->withInput();
        if ($data['art'] === 'ordentlich' && ! $gewerbe && empty($data['grund'])) {
            return $back('Bei Wohnraum braucht die ordentliche Kündigung einen gesetzlichen Grund (§ 573 BGB). Bitte den Grund wählen.');
        }
        if ($begruendung === '' && ! ($data['art'] === 'ordentlich' && $gewerbe) && $data['art'] !== 'fristlos_zahlung') {
            return $back('Bitte die Gründe der Kündigung genau beschreiben. Sie müssen im Schreiben stehen (§ 573 Abs. 3, § 569 Abs. 4 BGB).');
        }

        $start = $lease->start_date?->format('Y-m-d') ?? $data['zugang'];
        $frist = Kuendigung::ordentlichesEnde((string) $lease->lease_type, $start, $data['zugang']);
        $verzug = $this->verzug($lease, date('Y-m-d'));
        if ($data['art'] === 'fristlos_zahlung' && $verzug['summe'] <= 0.004) {
            return $back('Im Mieterkonto ist keine Miete offen. Eine Kündigung wegen Zahlungsverzug ist so nicht möglich.');
        }
        $fristlos = $data['art'] !== 'ordentlich';
        $raeumung = $data['raeumung'] ?? null;
        if ($fristlos && $raeumung === null) {
            $raeumung = (new \DateTimeImmutable($data['zugang']))->modify('+14 days')->format('Y-m-d');
        }

        $base = Bescheinigung::basis($lease);
        Audit::log('letter.termination', $lease, Kuendigung::ARTEN[$data['art']].' erstellt'.($fristlos ? '' : ' (Ende '.Bescheinigung::de($frist['ende']).')').': '.$lease->title());

        return view('bescheinigung.kuendigung', $base + [
            'art' => $data['art'],
            'grund' => $data['grund'] ?? null,
            'grundText' => isset($data['grund']) ? Kuendigung::GRUENDE[$data['grund']] : null,
            'begruendung' => $begruendung,
            'zugang' => $data['zugang'],
            'briefdatum' => $data['briefdatum'],
            'raeumung' => $raeumung,
            'hilfsweise' => $fristlos && (bool) ($data['hilfsweise'] ?? false),
            'frist' => $frist,
            'verzug' => $verzug,
            'gewerbe' => $gewerbe,
        ]);
    }

    public function wohnungsgeber(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'vorgang' => ['required', 'in:einzug,auszug'],
            'datum' => ['required', 'date_format:Y-m-d'],
            'personen' => ['required', 'string', 'max:3000'],
            'briefdatum' => ['required', 'date_format:Y-m-d'],
        ], [
            'vorgang.*' => 'Bitte Einzug oder Auszug wählen.',
            'datum.*' => 'Bitte das Datum des Ein- bzw. Auszugs angeben.',
            'personen.*' => 'Bitte mindestens eine Person eintragen (eine je Zeile).',
            'briefdatum.*' => 'Bitte das Datum der Bestätigung angeben.',
        ]);
        $personen = Bescheinigung::personen($data['personen']);
        if ($personen === []) {
            return redirect()->route('bescheinigung.index', $lease)->withErrors(['personen' => 'Bitte mindestens eine Person eintragen (eine je Zeile).']);
        }

        $base = Bescheinigung::basis($lease);
        Audit::log('certificate.landlord', $lease, 'Wohnungsgeberbestätigung ('.Bescheinigung::VORGAENGE[$data['vorgang']].' am '.Bescheinigung::de($data['datum']).') erstellt: '.$lease->title());

        return view('bescheinigung.wohnungsgeber', $base + [
            'vorgang' => $data['vorgang'],
            'datum' => $data['datum'],
            'personen' => $personen,
            'briefdatum' => $data['briefdatum'],
        ]);
    }

    public function mietschuldenfrei(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'stichtag' => ['required', 'date_format:Y-m-d'],
            'puenktlich' => ['nullable', 'boolean'],
            'zweck' => ['nullable', 'string', 'max:190'],
        ], ['stichtag.*' => 'Bitte einen Stichtag angeben.']);
        if ($data['stichtag'] > date('Y-m-d')) {
            return redirect()->route('bescheinigung.index', $lease)->withErrors(['stichtag' => 'Der Stichtag darf nicht in der Zukunft liegen.']);
        }

        RentLedger::syncAllIfStale();
        $offen = Bescheinigung::offenBis($lease, $data['stichtag']);
        if ($offen > 0.004) {
            return redirect()->route('bescheinigung.index', $lease)->withErrors(['stichtag' => 'Bis zum '.Bescheinigung::de($data['stichtag']).' sind noch '.number_format($offen, 2, ',', '.').' € offen. Eine Mietschuldenfreiheitsbescheinigung ist erst möglich, wenn alles bezahlt ist.']);
        }

        $base = Bescheinigung::basis($lease);
        Audit::log('certificate.no_arrears', $lease, 'Mietschuldenfreiheitsbescheinigung zum '.Bescheinigung::de($data['stichtag']).' erstellt: '.$lease->title());

        return view('bescheinigung.mietschuldenfrei', $base + [
            'stichtag' => $data['stichtag'],
            'puenktlich' => (bool) ($data['puenktlich'] ?? false),
            'zweck' => trim((string) ($data['zweck'] ?? '')),
            'miete' => Bescheinigung::miete($lease, $data['stichtag']),
        ]);
    }
}
