<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 47: Automatischer Kontoabruf per FinTS (PIN/TAN). Je Bankkonto eine Verbindung.
 * Anmeldename, PIN und der gespeicherte Sitzungsstand liegen verschlüsselt (APP_KEY).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_connections')) {
            Schema::create('bank_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_bank_account_id')->unique()->constrained('owner_bank_accounts')->cascadeOnDelete();
                $table->string('url', 255);
                $table->string('bank_code', 8);
                $table->text('username');                 // verschlüsselt
                $table->text('pin');                      // verschlüsselt
                $table->string('tan_mode', 10)->nullable();
                $table->string('tan_medium', 100)->nullable();
                $table->longText('state')->nullable();    // verschlüsselt: gespeicherte FinTS-Sitzung (BPD/UPD, Kundensystem-ID)
                $table->longText('pending')->nullable();  // verschlüsselt: offene Aktion, die auf eine TAN wartet
                $table->string('status', 20)->default('neu'); // neu | bereit | tan | fehler
                $table->boolean('active')->default(true);
                $table->timestamp('last_fetch_at')->nullable();
                $table->date('last_to')->nullable();
                $table->text('last_error')->nullable();
                $table->text('last_message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
    }
};
