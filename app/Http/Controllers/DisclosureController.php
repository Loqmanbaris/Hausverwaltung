<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Interessenten\Interessenten;
use App\Support\Mail\Notify;
use Illuminate\Http\Request;

/**
 * Öffentliche Seite für die Selbstauskunft (Paket 31). Zugang nur mit dem geheimen Link aus der E-Mail,
 * der Link gilt 14 Tage und nur für eine Abgabe.
 */
class DisclosureController extends Controller
{
    public function form(string $token)
    {
        $d = Interessenten::byToken($token);
        if ($d === null) {
            return $this->gone();
        }
        $d->applicant->loadMissing(['unit.property', 'owner']);

        return $this->page('applicants.public', ['d' => $d, 'a' => $d->applicant, 'token' => $token, 'values' => []]);
    }

    public function submit(Request $request, string $token)
    {
        $d = Interessenten::byToken($token);
        if ($d === null) {
            return $this->gone();
        }
        $a = $d->applicant;
        $a->loadMissing(['unit.property', 'owner']);
        if (! $request->boolean('consent')) {
            return $this->page('applicants.public', ['d' => $d, 'a' => $a, 'token' => $token, 'values' => $request->except('_token'), 'errors2' => ['Bitte bestätigen Sie, dass Sie die Hinweise zum Datenschutz gelesen haben.']]);
        }
        [$data, $errors] = Interessenten::clean($request->all());
        if ($errors !== []) {
            return $this->page('applicants.public', ['d' => $d, 'a' => $a, 'token' => $token, 'values' => $request->except('_token'), 'errors2' => array_values($errors)]);
        }
        Interessenten::submit($a, $data, 'ausgefuellt', true);
        Audit::log('applicant.disclosure', $a, 'Selbstauskunft online ausgefüllt: '.$a->name(), null, null, 'Interessent');
        try {
            Notify::message('Selbstauskunft eingegangen', $a->name().' hat die Selbstauskunft für '.$a->unitLabel().' ausgefüllt.');
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->page('applicants.public-done', ['a' => $a]);
    }

    private function gone()
    {
        return response()->view('applicants.public-gone', [], 410)->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store, private');
    }
}
