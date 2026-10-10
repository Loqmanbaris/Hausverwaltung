<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket 19: eigene Übersicht je Benutzer (Reihenfolge, sichtbare Bereiche, Schnellzugriff, Merkzettel)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'dashboard_layout')) {
                $table->text('dashboard_layout')->nullable();
            }
            if (! Schema::hasColumn('users', 'dashboard_note')) {
                $table->text('dashboard_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dashboard_layout', 'dashboard_note']);
        });
    }
};
