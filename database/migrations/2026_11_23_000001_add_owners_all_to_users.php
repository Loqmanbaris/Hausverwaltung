<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Recht „Alle Objekte“: Mitarbeiter sieht alle Eigentümer, Objekte, Mieter, Nebenkosten usw. wie ein Administrator,
// Verwaltungsfunktionen (Benutzer, Einstellungen, DATEV, Sicherungen) bleiben beim Administrator (Wunsch Inhaber 19.11.2026: Gillian)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'owners_all')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('owners_all')->default(false);
            });
        }
        DB::table('users')->where('name', 'like', 'Gillian%')->update(['owners_all' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'owners_all')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('owners_all');
            });
        }
    }
};
