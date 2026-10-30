<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Applicant;
use App\Models\ApplicantViewing;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Unit;
use App\Support\Audit;
use App\Support\Interessenten\Interessenten;
use App\Support\Interessenten\Letters;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Mietinteressenten: Liste, Anlegen, Besichtigungen, Selbstauskunft, Zu- und Absage, Mieter anlegen (Paket 31). */
class ApplicantController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $status = (string) $request->query('stand', 'offen');
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $base = Applicant::query()->visibleTo($user)->when($ownerId, fn ($q) => $q->where('applicants.owner_id', $ownerId));
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $items = $base->with(['unit.property', 'disclosure', 'viewings'])
            ->when($status === 'offen', fn ($q) => $q->whereIn('status', Applicant::OPEN))
            ->when(array_key_exists($status, Applicant::STATUS), fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->limit(300)->get();

        $pending = Inquiry::query()->visibleTo($user)->whereIn('status', ['neu', 'in_bearbeitung'])
            ->where(fn ($q) => $q->whereNull('topic')->orWhere('topic', '!=', 'schaden'))
            ->whereNotIn('id', Applicant::query()->whereNotNull('inquiry_id')->select('inquiry_id'))
            ->with('listing.unit.property')->orderByDesc('created_at')->limit(30)->get();

        return view('applicants.index', [
            'items' => $items, 'counts' => $counts, 'status' => $status, 'pending' => $pending,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']), 'ownerId' => $ownerId,
        ]);
    }

    public function create(Request $request)
    {
        $a = new Applicant(['source' => 'manuell', 'status' => 'neu']);
        $unit = (int) $request->query('einheit');
        if ($unit) {
            $a->unit_id = $unit;
        }
        $inq = (int) $request->query('anfrage');
        if ($inq && ($q = Inquiry::query()->visibleTo($this->me())->find($inq))) {
            [$first, $last] = Interessenten::splitName((string) $q->name);
            $a->fill(['first_name' => $first, 'last_name' => $last, 'email' => $q->email, 'phone' => $q->phone, 'persons' => $q->persons, 'move_in' => $q->date_from, 'message' => $q->message, 'source' => 'webseite', 'inquiry_id' => $q->id, 'listing_id' => $q->listing_id, 'unit_id' => $q->listing?->unit_id, 'owner_id' => $q->owner_id]);
        }

        return view('applicants.form', $this->formData($a));
    }

    public function store(Request $request)
    {
        [$data, $ownerId] = $this->validated($request);
        $a = Applicant::create($data + ['owner_id' => $ownerId, 'status' => 'neu', 'created_by' => $this->me()->id, 'inquiry_id' => $this->inquiryId($request), 'listing_id' => (int) $request->input('listing_id') ?: null]);
        Interessenten::touch($a);
        Audit::log('applicant.created', $a, 'Interessent angelegt: '.$a->name());

        return redirect()->route('applicants.show', $a)->with('status', 'Der Interessent wurde angelegt.');
    }

    public function fromInquiry(Inquiry $inquiry)
    {
        abort_unless(Inquiry::query()->visibleTo($this->me())->whereKey($inquiry->id)->exists(), 404);
        try {
            $a = Interessenten::fromInquiry($inquiry, $this->me());
        } catch (RuntimeException $e) {
            return redirect()->route('applicants.create', ['anfrage' => $inquiry->id])->withErrors(['owner_id' => $e->getMessage()]);
        }

        return redirect()->route('applicants.show', $a)->with('status', 'Die Anfrage ist jetzt ein Interessent.');
    }

    public function show(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $applicant->load(['unit.property', 'owner', 'disclosure', 'viewings', 'tenant', 'listing']);
        $d = $applicant->disclosure;
        $token = $d ? Interessenten::currentToken($d) : null;

        return view('applicants.show', [
            'a' => $applicant, 'd' => $d, 'data' => $d && $d->isFilled() ? $d->data() : [],
            'link' => $token ? Interessenten::link($token) : null, 'ratio' => Interessenten::incomeRatio($applicant),
        ]);
    }

    public function edit(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);

        return view('applicants.form', $this->formData($applicant));
    }

    public function update(Request $request, Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        [$data, $ownerId] = $this->validated($request, $applicant);
        $applicant->update($data + ['owner_id' => $ownerId]);
        Audit::log('applicant.updated', $applicant, 'Interessent geändert: '.$applicant->name());

        return redirect()->route('applicants.show', $applicant)->with('status', 'Gespeichert.');
    }

    public function destroy(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        Audit::log('applicant.deleted', $applicant, 'Interessent gelöscht: '.$applicant->name());
        $applicant->delete();

        return redirect()->route('applicants.index')->with('status', 'Der Interessent und seine Angaben wurden gelöscht.');
    }

    public function status(Request $request, Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Applicant::STATUS))], 'reason' => ['nullable', 'string', 'max:190']]);
        Interessenten::setStatus($applicant, $data['status'], $data['reason'] ?? null);

        return back()->with('status', 'Stand: '.$applicant->statusLabel().'.');
    }

    // ------------------------------------------------------------------ Besichtigungen

    public function viewingStore(Request $request, Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'place' => ['nullable', 'string', 'max:190'], 'note' => ['nullable', 'string', 'max:1000']], ['starts_at.required' => 'Bitte Datum und Uhrzeit der Besichtigung angeben.']);
        $v = $applicant->viewings()->create(['unit_id' => $applicant->unit_id, 'starts_at' => $data['starts_at'], 'place' => $data['place'] ?? null, 'note' => $data['note'] ?? null, 'status' => 'geplant']);
        Interessenten::advance($applicant, 'besichtigung');
        Audit::log('applicant.viewing', $applicant, 'Besichtigung geplant: '.$applicant->name().' am '.$v->starts_at->format('d.m.Y H:i'));

        return redirect()->route('applicants.show', $applicant)->with('status', 'Der Termin ist eingetragen. Du kannst ihn dem Interessenten jetzt per E-Mail schicken.');
    }

    public function viewingUpdate(Request $request, ApplicantViewing $viewing)
    {
        $applicant = $viewing->applicant;
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ApplicantViewing::STATUS))]]);
        $viewing->update(['status' => $data['status']]);

        return back()->with('status', 'Termin: '.$viewing->statusLabel().'.');
    }

    public function viewingDestroy(ApplicantViewing $viewing)
    {
        $this->assertOwnerAccess((int) $viewing->applicant->owner_id);
        $viewing->delete();

        return back()->with('status', 'Der Termin wurde gelöscht.');
    }

    // ------------------------------------------------------------------ Selbstauskunft

    public function disclosureRequest(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        try {
            Interessenten::requestDisclosure($applicant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['disclosure' => $e->getMessage()]);
        }

        return redirect()->route('applicants.show', $applicant)->with('status', 'Der Link ist erzeugt. Schicke ihn per E-Mail oder kopiere ihn.');
    }

    public function disclosureRenew(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        try {
            Interessenten::renewDisclosure($applicant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['disclosure' => $e->getMessage()]);
        }

        return back()->with('status', 'Ein neuer Link ist erzeugt. Der alte gilt nicht mehr.');
    }

    public function disclosurePrint(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $applicant->load(['unit.property', 'owner']);

        return view('applicants.form-print', ['a' => $applicant]);
    }

    public function disclosureManual(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $d = $applicant->disclosure;

        return view('applicants.manual', ['a' => $applicant, 'values' => $d && $d->isFilled() ? $d->data() : [], 'consent' => (bool) $d?->consent_at]);
    }

    public function disclosureManualStore(Request $request, Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        [$data, $errors] = Interessenten::clean($request->all());
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }
        Interessenten::submit($applicant, $data, 'manuell', $request->boolean('consent'));
        Audit::log('applicant.disclosure', $applicant, 'Selbstauskunft von Hand eingetragen: '.$applicant->name());

        return redirect()->route('applicants.show', $applicant)->with('status', 'Die Selbstauskunft ist gespeichert.');
    }

    // ------------------------------------------------------------------ Zusage, Absage, Mietvertrag

    public function letter(Applicant $applicant, string $kind)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        abort_unless(in_array($kind, ['zusage', 'absage'], true), 404);

        return view('applicants.letter', Letters::data($applicant, $kind));
    }

    public function convert(Applicant $applicant)
    {
        $this->assertOwnerAccess((int) $applicant->owner_id);
        $tenant = Interessenten::makeTenant($applicant, $this->me());

        return redirect()->route('leases.create', array_filter(['einheit' => $applicant->unit_id, 'mieter' => $tenant->id]))
            ->with('status', 'Der Mieter ist angelegt. Jetzt den Mietvertrag erfassen.');
    }

    // ------------------------------------------------------------------ Hilfen

    private function inquiryId(Request $request): ?int
    {
        $id = (int) $request->input('inquiry_id');
        if ($id && Inquiry::query()->visibleTo($this->me())->whereKey($id)->exists() && ! Applicant::query()->where('inquiry_id', $id)->exists()) {
            return $id;
        }

        return null;
    }

    private function formData(Applicant $a): array
    {
        return [
            'a' => $a,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'units' => Unit::visibleTo($this->me())->with('property')->orderBy('property_id')->orderBy('label')->get(),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function validated(Request $request, ?Applicant $existing = null): array
    {
        $d = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:60'],
            'persons' => ['nullable', 'integer', 'between:1,20'], 'move_in' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['required', Rule::in(array_keys(Applicant::SOURCES))], 'message' => ['nullable', 'string', 'max:3000'], 'note' => ['nullable', 'string', 'max:3000'],
            'unit_id' => ['nullable', 'integer'], 'owner_id' => ['nullable', 'integer'],
        ], ['last_name.required' => 'Bitte mindestens den Nachnamen eintragen.', 'email.email' => 'Die E-Mail-Adresse ist ungültig.']);

        $ownerId = null;
        if (! empty($d['unit_id'])) {
            $unit = Unit::visibleTo($this->me())->with('property')->find((int) $d['unit_id']);
            abort_if($unit === null, 404);
            $ownerId = (int) $unit->property?->owner_id;
        } else {
            $d['unit_id'] = null;
            $ownerId = (int) ($d['owner_id'] ?? $existing?->owner_id ?? 0);
        }
        if ($ownerId === 0) {
            $only = $this->visibleOwnerIds();
            $ownerId = count($only) === 1 ? $only[0] : 0;
        }
        $this->assertOwnerAccess($ownerId);
        unset($d['owner_id']);
        foreach (['first_name', 'email', 'phone', 'message', 'note'] as $k) {
            $d[$k] = isset($d[$k]) && trim((string) $d[$k]) !== '' ? trim((string) $d[$k]) : null;
        }

        return [$d, $ownerId];
    }
}
