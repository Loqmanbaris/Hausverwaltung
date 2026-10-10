<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Inquiry;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Anfragen aus dem Kontaktformular der Webseite. */
class InquiryController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['neu', 'in_bearbeitung', 'erledigt', 'alle'], true) ? $request->query('status') : 'offen';
        $q = Inquiry::visibleTo($this->me())->with('listing.unit.property');
        $counts = (clone $q)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $items = $q->when($status === 'offen', fn ($w) => $w->whereIn('status', ['neu', 'in_bearbeitung']))
            ->when(in_array($status, ['neu', 'in_bearbeitung', 'erledigt'], true), fn ($w) => $w->where('status', $status))
            ->orderByRaw("field(status, 'neu', 'in_bearbeitung', 'erledigt')")->orderByDesc('created_at')->limit(300)->get();

        return view('inquiries.index', compact('items', 'counts', 'status'));
    }

    public function show(Inquiry $inquiry)
    {
        $this->access($inquiry);

        return view('inquiries.show', ['inquiry' => $inquiry->load('listing.unit.property')]);
    }

    public function update(Request $request, Inquiry $inquiry)
    {
        $this->access($inquiry);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Inquiry::STATUS))],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $inquiry->update(['status' => $data['status'], 'note' => trim((string) ($data['note'] ?? '')) ?: null]);

        return redirect()->route('inquiries.show', $inquiry)->with('status', 'Gespeichert.');
    }

    public function destroy(Inquiry $inquiry)
    {
        $this->access($inquiry);
        Audit::log('inquiry.deleted', $inquiry, 'Anfrage gelöscht (Nr. '.$inquiry->id.')');
        $inquiry->delete();

        return redirect()->route('inquiries.index')->with('status', 'Anfrage gelöscht.');
    }

    private function access(Inquiry $inquiry): void
    {
        if (! $this->me()->isAdmin()) {
            abort_if($inquiry->owner_id === null, 404);
            $this->assertOwnerAccess((int) $inquiry->owner_id);
        }
    }
}
