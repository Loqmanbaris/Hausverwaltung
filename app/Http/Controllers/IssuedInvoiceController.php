<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\IssuedInvoice;
use App\Models\Owner;
use App\Models\RentCharge;
use App\Support\ERechnung\Ansicht;
use App\Support\ERechnung\Rechnungen;
use App\Support\Pdf\HtmlPdf;
use Illuminate\Http\Request;
use RuntimeException;

/** E-Rechnung (XRechnung) für umsatzsteuerpflichtige Vermietung an Unternehmen (Paket 37). */
class IssuedInvoiceController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $user = $this->me();
        $leases = Rechnungen::eligibleLeases($user)->get();
        $owners = $leases->pluck('owner')->filter()->unique('id');
        $issues = [];
        foreach ($owners as $o) {
            $i = Rechnungen::ownerIssues($o);
            if ($i !== []) {
                $issues[$o->id] = ['owner' => $o, 'items' => $i];
            }
        }

        return view('erechnung.index', [
            'leases' => $leases, 'pending' => Rechnungen::pending($user), 'issues' => $issues,
            'invoices' => IssuedInvoice::query()->visibleTo($user)->with(['lease.unit', 'lease.tenant'])->orderByDesc('issued_on')->orderByDesc('id')->limit(60)->get(),
        ]);
    }

    public function create(RentCharge $charge)
    {
        $charge->loadMissing('lease');
        $this->assertOwnerAccess((int) $charge->owner_id);
        try {
            $inv = Rechnungen::createFromCharge($charge, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['einvoice' => $e->getMessage()]);
        }

        return redirect()->route('erechnung.index')->with('status', 'Die Rechnung '.$inv->number.' ist erstellt.');
    }

    public function createAll()
    {
        $made = 0;
        $errors = [];
        foreach (Rechnungen::pending($this->me(), null, 30, 10) as $c) {
            try {
                Rechnungen::createFromCharge($c, $this->me());
                $made++;
            } catch (RuntimeException $e) {
                $errors[$c->lease?->title() ?? 'Vertrag'] = $e->getMessage();
            }
        }
        $back = redirect()->route('erechnung.index');
        if ($errors !== []) {
            $back->withErrors(['einvoice' => collect($errors)->map(fn ($m, $k) => $k.': '.$m)->unique()->take(3)->implode(' | ')]);
        }

        return $back->with('status', $made.' Rechnung(en) erstellt.');
    }

    public function xml(IssuedInvoice $invoice)
    {
        $this->assertOwnerAccess((int) $invoice->owner_id);

        return response($invoice->xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.Rechnungen::fileName($invoice, 'xml').'"', 'Cache-Control' => 'private, no-store']);
    }

    public function view(IssuedInvoice $invoice)
    {
        $this->assertOwnerAccess((int) $invoice->owner_id);

        return view('erechnung.print', ['inv' => $invoice, 'd' => Ansicht::data($invoice)]);
    }

    public function pdf(IssuedInvoice $invoice)
    {
        $this->assertOwnerAccess((int) $invoice->owner_id);
        $html = view('erechnung.print', ['inv' => $invoice, 'd' => Ansicht::data($invoice)])->render();

        return response(HtmlPdf::render($html, $invoice->kindLabel().' '.$invoice->number), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.Rechnungen::fileName($invoice, 'pdf').'"', 'Cache-Control' => 'private, no-store']);
    }

    public function credit(IssuedInvoice $invoice)
    {
        $this->assertOwnerAccess((int) $invoice->owner_id);
        try {
            $c = Rechnungen::credit($invoice, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['einvoice' => $e->getMessage()]);
        }

        return redirect()->route('erechnung.index')->with('status', 'Die Gutschrift '.$c->number.' ist erstellt. Die Rechnung '.$invoice->number.' gilt als storniert.');
    }
}
