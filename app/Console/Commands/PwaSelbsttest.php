<?php

namespace App\Console\Commands;

use App\Http\Controllers\PwaController;
use App\Support\Pwa\Icons;
use App\Support\Tag\MeinTag;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 43 (App installieren, neue Optik von „Mein Tag“). */
class PwaSelbsttest extends Command
{
    protected $signature = 'pwa:selbsttest';

    protected $description = 'Selbsttest App-Installation und Mein Tag (Paket 43)';

    public function handle(): int
    {
        $c = new PwaController();
        $manifest = json_decode((string) $c->manifest()->getContent(), true);
        $worker = $c->worker();
        $i192 = Icons::png(192);
        $i512 = Icons::png(512);
        $s192 = $i192 !== null ? @getimagesizefromstring($i192) : false;
        $s512 = $i512 !== null ? @getimagesizefromstring($i512) : false;
        $tz = MeinTag::tz();
        $checks = [
            'Manifest gültig' => is_array($manifest) && ($manifest['display'] ?? '') === 'standalone' && ($manifest['start_url'] ?? '') === '/mein-tag' && count($manifest['icons'] ?? []) >= 2,
            'Symbole sind PNG in richtiger Größe' => $s192 !== false && $s192[0] === 192 && $s192[1] === 192 && $s512 !== false && $s512[0] === 512 && $s512[1] === 512 && $s512['mime'] === 'image/png',
            'Service Worker' => str_contains((string) $worker->getContent(), "addEventListener('fetch'") && $worker->headers->get('Service-Worker-Allowed') === '/',
            'Seiten' => Route::has('pwa.manifest') && Route::has('pwa.worker') && Route::has('pwa.icon') && Route::has('pwa.offline'),
            'Offline-Seite' => str_contains((string) $c->offline()->getContent(), 'Keine Verbindung'),
            'Begrüßung' => MeinTag::greeting(new DateTimeImmutable('2026-10-30 08:00', $tz)) === 'Guten Morgen'
                && MeinTag::greeting(new DateTimeImmutable('2026-10-30 13:00', $tz)) === 'Guten Tag'
                && MeinTag::greeting(new DateTimeImmutable('2026-10-30 20:30', $tz)) === 'Guten Abend',
            'Ansichten' => view()->exists('tag.index') && view()->exists('layouts.app'),
        ];
        $this->line('Selbsttest Paket 43 (App installieren, Mein Tag)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
