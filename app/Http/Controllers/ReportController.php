<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Owner;
use App\Support\Audit;
use App\Support\Pdf\HtmlPdf;
use App\Support\Report\Jahresbericht;
use Illuminate\Http\Request;

/** Jahresbericht je Eigentümer und Jahr (Paket 28): Ansicht, Druck, PDF. */
class ReportController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        [$owners, $owner, $year] = $this->params($request);

        return view('report.index', ['owners' => $owners, 'owner' => $owner, 'year' => $year, 'data' => $owner ? Jahresbericht::build($owner, $year) : null]);
    }

    public function print(Request $request)
    {
        [, $owner, $year] = $this->params($request);
        abort_if($owner === null, 404);

        return view('report.print', ['data' => Jahresbericht::build($owner, $year)]);
    }

    public function pdf(Request $request)
    {
        [, $owner, $year] = $this->params($request);
        abort_if($owner === null, 404);
        $html = view('report.print', ['data' => Jahresbericht::build($owner, $year)])->render();
        Audit::log('report.exported', $owner, 'Jahresbericht '.$year.' als PDF heruntergeladen: '.$owner->name);

        return response(HtmlPdf::render($html, 'Jahresbericht '.$year.' '.$owner->name), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Jahresbericht::pdfName($owner, $year).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: ?Owner, 2: int} */
    private function params(Request $request): array
    {
        $owners = $this->visibleOwners()->get();
        $ownerId = (int) $request->query('eigentuemer');
        $owner = $ownerId ? $owners->firstWhere('id', $ownerId) : ($owners->count() === 1 ? $owners->first() : null);
        $year = (int) $request->query('jahr', (int) date('Y') - 1);
        if ($year < 2000 || $year > (int) date('Y')) {
            $year = (int) date('Y') - 1;
        }

        return [$owners, $owner, $year];
    }
}
