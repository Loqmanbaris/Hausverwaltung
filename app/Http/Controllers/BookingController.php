<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\UnitBooking;
use App\Support\Audit;
use App\Support\Listings\Occupancy;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Belegungskalender einer Einheit (vor allem Montage-Wohnungen). */
class BookingController extends Controller
{
    use ScopesOwners;

    public function index(Request $request, Unit $unit)
    {
        $this->access($unit);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('monat')) ? $request->query('monat') : date('Y-m');
        $from = $month.'-01';
        $to = (new DateTimeImmutable($from))->modify('+4 months')->format('Y-m-d');
        $inquiry = $request->query('anfrage') ? Inquiry::visibleTo($this->me())->find((int) $request->query('anfrage')) : null;

        return view('bookings.index', [
            'unit' => $unit->load('property'),
            'month' => $month,
            'calendar' => Occupancy::calendar($unit->id, $month, 4),
            'periods' => Occupancy::periods($unit->id, date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+2 years'))),
            'prev' => (new DateTimeImmutable($from))->modify('-1 month')->format('Y-m'),
            'next' => (new DateTimeImmutable($from))->modify('+1 month')->format('Y-m'),
            'inquiry' => $inquiry,
            'listing' => \App\Models\Listing::query()->where('unit_id', $unit->id)->orderByRaw("field(status, 'online', 'entwurf', 'offline')")->first(),
            'to' => $to,
        ]);
    }

    public function store(Request $request, Unit $unit)
    {
        $this->access($unit);
        $data = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after:date_from'],
            'status' => ['required', Rule::in(array_keys(UnitBooking::STATUS))],
            'label' => ['nullable', 'string', 'max:190'],
            'persons' => ['nullable', 'integer', 'between:1,50'],
            'note' => ['nullable', 'string', 'max:2000'],
            'inquiry_id' => ['nullable', 'integer'],
        ], [
            'date_from.*' => 'Bitte den Anreisetag angeben.',
            'date_to.after' => 'Der Abreisetag muss nach dem Anreisetag liegen.',
            'date_to.*' => 'Bitte den Abreisetag angeben.',
        ]);
        $conflict = Occupancy::conflict($unit->id, $data['date_from'], $data['date_to']);
        if ($conflict) {
            return back()->withInput()->withErrors(['date_from' => 'In diesem Zeitraum ist die Einheit schon belegt: '.self::describe($conflict).'.']);
        }
        $inquiryId = null;
        if (! empty($data['inquiry_id'])) {
            $inq = Inquiry::visibleTo($this->me())->find((int) $data['inquiry_id']);
            if ($inq) {
                $inquiryId = $inq->id;
                if ($inq->status === 'neu') {
                    $inq->update(['status' => 'in_bearbeitung']);
                }
            }
        }
        $b = UnitBooking::create([
            'unit_id' => $unit->id,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'status' => $data['status'],
            'label' => trim((string) ($data['label'] ?? '')) ?: null,
            'persons' => $data['persons'] ?? null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'inquiry_id' => $inquiryId,
            'created_by' => $this->me()->id,
        ]);
        Audit::log('booking.created', $b, 'Belegung eingetragen: '.$unit->label.', '.$b->date_from->format('d.m.Y').' bis '.$b->date_to->format('d.m.Y'));

        return redirect()->route('bookings.index', ['unit' => $unit, 'monat' => substr($data['date_from'], 0, 7)])->with('status', 'Belegung eingetragen ('.$b->nights().' Nächte).');
    }

    public function update(Request $request, Unit $unit, UnitBooking $booking)
    {
        $this->access($unit);
        abort_unless((int) $booking->unit_id === (int) $unit->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(UnitBooking::STATUS))]]);
        $booking->update(['status' => $data['status']]);

        return back()->with('status', 'Status geändert: '.UnitBooking::STATUS[$data['status']].'.');
    }

    public function destroy(Unit $unit, UnitBooking $booking)
    {
        $this->access($unit);
        abort_unless((int) $booking->unit_id === (int) $unit->id, 404);
        Audit::log('booking.deleted', $booking, 'Belegung entfernt: '.$unit->label.', '.$booking->date_from->format('d.m.Y').' bis '.$booking->date_to->format('d.m.Y'));
        $booking->delete();

        return back()->with('status', 'Belegung entfernt.');
    }

    public static function describe(array $p): string
    {
        $to = $p['to'] === '9999-12-31' ? 'unbefristet' : 'bis '.date('d.m.Y', strtotime($p['to']));

        return ($p['label'] ?: (UnitBooking::STATUS[$p['status']] ?? 'Belegung')).', '.date('d.m.Y', strtotime($p['from'])).' '.$to;
    }

    private function access(Unit $unit): void
    {
        $unit->loadMissing('property');
        $this->assertOwnerAccess((int) $unit->property?->owner_id);
    }
}
