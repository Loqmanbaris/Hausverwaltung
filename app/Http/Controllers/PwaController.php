<?php

namespace App\Http\Controllers;

use App\Support\Pwa\Icons;

/** App zum Installieren (Paket 43): Manifest, Service Worker, Symbole und eine Offline-Seite. Alles ohne Daten, deshalb ohne Anmeldung. */
class PwaController extends Controller
{
    public function manifest()
    {
        $name = (string) config('app.name', 'Hausverwaltung');
        $data = [
            'id' => '/',
            'name' => $name,
            'short_name' => 'Verwaltung',
            'description' => 'Hausverwaltung: Mein Tag, Mieten, Aufträge',
            'lang' => 'de',
            'start_url' => '/mein-tag',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f5f6f8',
            'theme_color' => '#1b2338',
            'icons' => [
                ['src' => '/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Mein Tag', 'url' => '/mein-tag'],
                ['name' => 'Aufträge', 'url' => '/auftraege'],
                ['name' => 'Neuer Eintrag', 'url' => '/mein-tag#neu'],
            ],
        ];

        return response(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Der Service Worker speichert keine Seiten mit Daten. Er zeigt nur eine Offline-Seite, wenn keine Verbindung besteht. */
    public function worker()
    {
        $js = <<<'JS'
const CACHE = 'verwaltung-offline-v1';
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.add('/offline')).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  );
});
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.mode !== 'navigate') { return; }
  event.respondWith(fetch(req).catch(() => caches.match('/offline')));
});
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function icon(int $size)
    {
        $png = Icons::png($size);
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    public function offline()
    {
        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><title>Keine Verbindung</title>'
            .'<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#1b2338;color:#fff;font:16px/1.5 system-ui,sans-serif;text-align:center;padding:1.5rem}'
            .'h1{margin:.2rem 0 .6rem;font-size:1.6rem}p{color:#aab3c5;margin:0 0 1.2rem}button{background:#fff;color:#1b2338;border:0;border-radius:12px;padding:.8rem 1.4rem;font:inherit;font-weight:700}</style></head>'
            .'<body><div><h1>Keine Verbindung</h1><p>Die Hausverwaltung braucht Internet. Bitte prüfe deine Verbindung.</p><button onclick="location.reload()">Erneut versuchen</button></div></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }
}
