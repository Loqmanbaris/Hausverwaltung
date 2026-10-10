<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\InstallmentPlan;
use App\Models\InterestBooking;
use App\Models\Lease;
use App\Support\Audit;
use App\Support\Interest\Raten;
use App\Support\Interest\Verzugszinsen;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Verzugszinsen und Ratenvereinbarungen (Paket 14). */
class InterestController extends Controller
{
    use ScopesOwners;

    public function index(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        RentLedger::sync($lease);
        $lease->load(['unit.property', 'tenant']);
        $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('bis')) && $request->query('bis') <= date('Y-m-d') ? $request->query('bis') : date('Y-m-d');
        $mode = array_key_exists((string) $request->query('modus'), Verzugszinsen::MODES) ? $request->query('modus') : Verzugszinsen::modeFor($lease);
        $from = Verzugszinsen::lastBookedUntil($lease);

        return view('interest.index', [
            'lease' => $lease,
            'until' => $until,
            'from' => $from,
            'mode' => $mode,
            'calc' => Verzugszinsen::calculate($lease, $from, $until, $mode),
            'bookings' => InterestBooking::query()->where('lease_id', $lease->id)->orderByDesc('period_until')->get(),
            'rateNow' => Verzugszinsen::baseRateOn($until),
            'missingRate' => Verzugszinsen::missingCurrentRate(),
        ]);
    }

    public function book(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate(['until' => ['required', 'date_format:Y-m-d'], 'mode' => ['required', Rule::in(array_keys(Verzugszinsen::MODES))]]);
        try {
            $b = Verzugszinsen::book($lease, $data['until'], $data['mode'], $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['interest' => $e->getMessage()]);
        }

        return redirect()->route('interest.index', $lease)->with('status', 'Verzugszinsen von '.Money::format($b->amount).' sind als Forderung im Mieterkonto gebucht.');
    }

    public function cancel(InterestBooking $booking)
    {
        $this->assertOwnerAccess((int) $booking->owner_id);
        try {
            Verzugszinsen::cancel($booking);
        } catch (RuntimeException $e) {
            return back()->withErrors(['interest' => $e->getMessage()]);
        }

        return back()->with('status', 'Zinsbuchung storniert.');
    }

    /* ---------- Raten ---------- */

    public function planCreate(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        RentLedger::sync($lease);
        $lease->load(['unit.property', 'tenant']);
        $overdue = (float) RentLedger::balance($lease)['overdue'];
        $debt = $request->query('betrag') !== null ? Money::parse($request->query('betrag')) : number_format($overdue, 2, '.', '');
        $count = $request->query('anzahl') !== null && $request->query('anzahl') !== '' ? (int) $request->query('anzahl') : null;
        $rate = $request->query('rate') !== null && $request->query('rate') !== '' ? Money::parse($request->query('rate')) : null;
        $first = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('erste')) ? $request->query('erste') : date('Y-m-15', strtotime('first day of next month'));
        $schedule = [];
        $error = null;
        if ($count === null && $rate === null) {
            $count = 6;
        }
        try {
            $schedule = Raten::schedule((string) $debt, $count, $rate, $first);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return view('interest.plan-form', [
            'lease' => $lease, 'overdue' => $overdue, 'debt' => $debt, 'count' => $count, 'rate' => $rate, 'first' => $first,
            'schedule' => $schedule, 'error' => $error, 'active' => Raten::activeFor($lease->id),
        ]);
    }

    public function planStore(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'debt' => ['required', 'string', 'max:20'],
            'count' => ['nullable', 'integer', 'between:1,120'],
            'rate' => ['nullable', 'string', 'max:20'],
            'first' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $debt = Money::parse($data['debt']);
        if ($debt === null || (float) $debt <= 0) {
            return back()->withInput()->withErrors(['debt' => 'Bitte einen gültigen Betrag angeben.']);
        }
        try {
            $schedule = Raten::schedule($debt, $data['count'] ?? null, isset($data['rate']) && $data['rate'] !== '' ? Money::parse($data['rate']) : null, $data['first']);
            $plan = Raten::create($lease, $debt, $schedule, $data['note'] ?? null, $this->me());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['plan' => $e->getMessage()]);
        }

        return redirect()->route('installments.show', $plan)->with('status', 'Ratenvereinbarung gespeichert. Bitte ausdrucken und unterschreiben lassen.');
    }

    public function planShow(InstallmentPlan $plan)
    {
        $this->assertOwnerAccess((int) $plan->owner_id);
        $plan->load(['installments', 'lease.unit.property', 'lease.tenant', 'lease.owner']);

        return view('interest.plan-show', ['plan' => $plan, 'state' => Raten::state($plan)]);
    }

    public function planPrint(InstallmentPlan $plan)
    {
        $this->assertOwnerAccess((int) $plan->owner_id);
        $plan->load(['installments', 'lease.unit.property', 'lease.tenant.contacts', 'lease.owner']);

        return view('interest.plan-print', ['plan' => $plan]);
    }

    public function planStatus(Request $request, InstallmentPlan $plan)
    {
        $this->assertOwnerAccess((int) $plan->owner_id);
        $status = $request->validate(['status' => ['required', Rule::in(['erfuellt', 'geplatzt', 'storniert', 'aktiv'])]])['status'];
        if ($status === 'aktiv' && InstallmentPlan::query()->where('lease_id', $plan->lease_id)->where('status', 'aktiv')->whereKeyNot($plan->id)->exists()) {
            return back()->withErrors(['plan' => 'Es läuft schon eine andere Ratenvereinbarung.']);
        }
        Raten::setStatus($plan, $status);

        return back()->with('status', 'Status geändert: '.InstallmentPlan::STATUS[$status].'.');
    }

    /* ---------- Basiszinssätze (Administrator) ---------- */

    public function rateStore(Request $request)
    {
        $data = $request->validate([
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'rate' => ['required', 'string', 'max:10'],
        ], ['valid_from.*' => 'Bitte das Datum angeben (1. Januar oder 1. Juli).']);
        $rate = str_replace(',', '.', trim($data['rate']));
        if (! is_numeric($rate) || abs((float) $rate) > 20) {
            return back()->withErrors(['rate' => 'Bitte den Basiszinssatz in Prozent angeben, z. B. 1,52 oder -0,88.']);
        }
        DB::table('base_interest_rates')->upsert([['valid_from' => $data['valid_from'], 'rate' => $rate, 'created_at' => now(), 'updated_at' => now()]], ['valid_from'], ['rate', 'updated_at']);
        Verzugszinsen::forgetRates();
        Audit::log('interest.base_rate', 'Basiszinssatz ab '.date('d.m.Y', strtotime($data['valid_from'])).': '.str_replace('.', ',', $rate).' %');

        return redirect()->to(route('dunning.index').'#basiszins')->with('status', 'Basiszinssatz gespeichert.');
    }

    public function rateDestroy(string $date)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 404);
        DB::table('base_interest_rates')->where('valid_from', $date)->delete();
        Audit::log('interest.base_rate', 'Basiszinssatz ab '.date('d.m.Y', strtotime($date)).' entfernt');

        return redirect()->to(route('dunning.index').'#basiszins')->with('status', 'Eintrag entfernt.');
    }
}
