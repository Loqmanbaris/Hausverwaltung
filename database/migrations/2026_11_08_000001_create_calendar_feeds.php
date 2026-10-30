<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 33: Kalender-Abo (iCal-Link) je Benutzer. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_feeds')) {
            Schema::create('calendar_feeds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->text('token_enc');                         // Schlüssel verschlüsselt, damit der Link wieder angezeigt werden kann
                $table->text('categories')->nullable();            // JSON: Liste der gewählten Arten, leer = alle
                $table->dateTime('last_fetched_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
    }
};
