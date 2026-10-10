<?php

namespace App\Console\Commands;

use App\Support\Pdf\HtmlPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 21 (E-Mail-Versand und PDF). */
class EmailSelbsttest extends Command
{
    protected $signature = 'email:selbsttest';

    protected $description = 'Selbsttest E-Mail-Versand und PDF (Paket 21)';

    public function handle(): int
    {
        $pdf = '';
        try {
            $pdf = HtmlPdf::render('<html><body><div class="page"><h1>Prüfung äöü €</h1><p>Test</p><table><tr><th>A</th><td class="n">1,00 €</td></tr></table></div></body></html>', 'Test');
        } catch (\Throwable $e) {
            $this->line('  '.$e->getMessage());
        }
        $checks = [
            'Tabelle' => Schema::hasTable('sent_emails'),
            'Seiten' => Route::has('mail.compose') && Route::has('mail.send') && Route::has('mail.settings') && Route::has('mail.index') && Route::has('documents.pdf'),
            'Ansichten' => view()->exists('mail.compose') && view()->exists('mail.settings') && view()->exists('mail.index'),
            'E-Mail-Bibliothek' => class_exists(\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport::class),
            'PDF erzeugen' => str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, '%%EOF'),
            'Bilder im PDF (GD)' => function_exists('imagecreatefromstring') && function_exists('imagejpeg'),
            'Verbindung nach außen (OpenSSL)' => extension_loaded('openssl'),
        ];
        $this->line('Selbsttest Paket 21 (E-Mail und PDF)');
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
