<?php

namespace App\Http\Controllers;

use App\Support\Settings;

/**
 * Öffentliche Seiten ohne Login: Startseite, Impressum, Datenschutzerklärung.
 */
class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'tagline' => Settings::get('home_tagline', 'Verwaltung von Wohn- und Gewerbeimmobilien.'),
            'email' => Settings::get('imprint_email'),
            'hasPhoto' => is_file(public_path('images/startseite.jpg')),
            'offers' => self::offers(),
        ]);
    }

    public function imprint()
    {
        return view('public.imprint', [
            's' => $this->imprintData(),
            'complete' => Settings::imprintComplete(),
        ]);
    }

    public function privacy()
    {
        return view('public.privacy', [
            's' => $this->imprintData(),
            'complete' => Settings::imprintComplete(),
        ]);
    }

    /** Anzahl Angebote je Art für die Startseite (Paket 11). */
    private static function offers(): array
    {
        try {
            return \App\Models\Listing::online()->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function imprintData(): array
    {
        $data = [];
        foreach (array_keys(Settings::FIELDS) as $key) {
            $data[$key] = Settings::get($key);
        }

        return $data;
    }
}
