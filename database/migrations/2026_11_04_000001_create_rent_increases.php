<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 27: Mieterhöhungen (Vergleichsmiete § 558, Modernisierung § 559, Index § 557b, Staffel, Vereinbarung).
 * Ein Datensatz je Verfahren. Beträge in Euro mit zwei Nachkommastellen (Kaltmiete, netto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rent_increases')) {
            Schema::create('rent_increases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('kind', 20);                              // vergleich | modernisierung | index | staffel | vereinbarung
                $table->string('status', 20)->default('entwurf');        // entwurf | erklaert | wirksam | abgelehnt | zurueckgezogen
                $table->date('notice_on');                               // Datum des Schreibens = Zugang beim Mieter
                $table->date('effective_from');                          // Erhöhung gilt ab
                $table->date('consent_until')->nullable();               // Zustimmungsfrist (nur Vergleichsmiete)
                $table->date('suit_until')->nullable();                  // Klagefrist auf Zustimmung (nur Vergleichsmiete)
                $table->decimal('old_cold', 12, 2);
                $table->decimal('new_cold', 12, 2);
                $table->decimal('area_sqm', 8, 2)->nullable();
                $table->text('data')->nullable();                        // JSON: Eingaben und Rechenweg
                $table->text('note')->nullable();                        // Zusatztext im Schreiben
                $table->date('decided_on')->nullable();                  // Zustimmung, Ablehnung oder Rücknahme
                $table->unsignedBigInteger('lease_rent_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['lease_id', 'status']);
                $table->index(['owner_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_increases');
    }
};
