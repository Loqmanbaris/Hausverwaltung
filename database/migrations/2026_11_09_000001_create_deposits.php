<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 34: Kautionsverwaltung (Raten, Anlage, Zinsen, Abrechnung beim Auszug). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deposits')) {
            Schema::create('deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->unique()->constrained('leases')->cascadeOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('form', 20)->default('konto');            // konto | sparbuch | buergschaft | bar
                $table->string('institution', 150)->nullable();          // Bank oder Bürge
                $table->string('account_note', 190)->nullable();         // z. B. letzte Stellen der Kontonummer
                $table->decimal('interest_rate', 6, 3)->nullable();      // Zinssatz in Prozent im Jahr
                $table->unsignedTinyInteger('installments')->default(1); // 1 bis 3 gleiche Monatsraten (§ 551 BGB)
                $table->string('status', 15)->default('offen');          // offen | abgerechnet
                $table->date('settled_on')->nullable();
                $table->decimal('claim', 12, 2)->default(0);             // Nachforderung an den Mieter nach der Abrechnung
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('deposit_entries')) {
            Schema::create('deposit_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deposit_id')->constrained('deposits')->cascadeOnDelete();
                $table->date('entry_on');
                $table->string('kind', 15);                              // einzahlung | zinsen | einbehalt | rueckzahlung
                $table->decimal('amount', 12, 2);                        // immer positiv
                $table->string('note', 190)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['deposit_id', 'entry_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_entries');
        Schema::dropIfExists('deposits');
    }
};
