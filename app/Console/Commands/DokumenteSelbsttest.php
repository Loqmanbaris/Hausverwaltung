<?php

namespace App\Console\Commands;

use App\Support\Documents\DocumentStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 22 (Dokumentenablage). */
class DokumenteSelbsttest extends Command
{
    protected $signature = 'dokumente:selbsttest';

    protected $description = 'Selbsttest Dokumentenablage (Paket 22)';

    public function handle(): int
    {
        $roundtrip = false;
        try {
            $p = DocumentStore::put('selbsttest');
            $roundtrip = DocumentStore::get($p) === 'selbsttest';
            DocumentStore::forget($p);
        } catch (\Throwable $e) {
            $this->line('  '.$e->getMessage());
        }
        $checks = [
            'Tabelle' => Schema::hasTable('documents'),
            'Seiten' => Route::has('documents.index') && Route::has('documents.store') && Route::has('documents.file'),
            'Ansichten' => view()->exists('documents.panel') && view()->exists('documents.index') && view()->exists('documents.edit'),
            'Speicher verschlüsselt lesen/schreiben' => $roundtrip,
        ];
        $this->line('Selbsttest Paket 22 (Dokumente)');
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
