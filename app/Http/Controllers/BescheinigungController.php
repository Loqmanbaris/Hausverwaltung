<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Support\Audit;
use App\Support\Bescheinigung\Bescheinigung;
use App\Support\Rent\Mieten;
use App\Support\Rent\RentLedger;
use Illuminate\Http\Request;

/** Wohnungsgeberbestätigung und Mietschuldenfreiheitsbescheinigung zum Mietvertrag (Paket 44). */
class BescheinigungController extends Controller
{
    use ScopesOwners;

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
