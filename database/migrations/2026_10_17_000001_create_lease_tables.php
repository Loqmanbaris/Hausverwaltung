<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 5a: Mietverträge, Mietstand (mit Verlauf), Soll-Stellung, Zahlungseingänge und deren Zuordnung.
 * Alle Beträge in Euro mit zwei Nachkommastellen. Bei Umsatzsteuer stehen die Bestandteile netto drin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leases')) {
            Schema::create('leases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();     // zur Rechteprüfung, aus dem Objekt übernommen
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->string('lease_type', 20)->default('wohnen');                         // wohnen | gewerbe | montage
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->unsignedTinyInteger('due_day')->default(3);                           // fällig am X. des Monats
                $table->unsignedSmallInteger('payment_term_days')->nullable();                // statt Fälligkeitstag: X Tage nach Beginn des Zeitraums
                $table->decimal('vat_rate', 5, 2)->default(0);                                // 0, 7 oder 19
                $table->string('adjustment_type', 10)->default('fest');                       // fest | staffel | index
                $table->decimal('index_base_value', 8, 2)->nullable();                        // Verbraucherpreisindex bei Vertragsbeginn bzw. letzter Anpassung
                $table->date('index_base_month')->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->date('deposit_received_on')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['unit_id', 'start_date']);
                $table->index(['owner_id', 'start_date']);
            });
        }

        if (! Schema::hasTable('lease_rents')) {
            Schema::create('lease_rents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->date('valid_from');
                $table->string('reason', 20)->default('beginn');       // beginn | erhoehung | staffel | index | vorauszahlung | korrektur
                $table->decimal('rent_cold', 12, 2)->default(0);       // Kaltmiete
                $table->decimal('prepay_operating', 12, 2)->default(0); // Nebenkosten-Vorauszahlung
                $table->decimal('prepay_heating', 12, 2)->default(0);  // Heizkosten-Vorauszahlung
                $table->decimal('parking', 12, 2)->default(0);         // Stellplatz
                $table->decimal('garage', 12, 2)->default(0);          // Garage
                $table->decimal('furniture', 12, 2)->default(0);       // Möblierungszuschlag
                $table->decimal('index_value', 8, 2)->nullable();      // bei Indexanpassung: neuer Indexwert
                $table->date('index_month')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['lease_id', 'valid_from']);
            });
        }

        if (! Schema::hasTable('rent_charges')) {
            Schema::create('rent_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->date('period');                                 // erster Tag des Monats
                $table->date('period_from');                            // bei anteiligen Monaten später
                $table->date('period_to');                              // bei anteiligen Monaten früher
                $table->date('due_date');
                $table->decimal('rent_cold', 12, 2)->default(0);
                $table->decimal('prepay_operating', 12, 2)->default(0);
                $table->decimal('prepay_heating', 12, 2)->default(0);
                $table->decimal('parking', 12, 2)->default(0);
                $table->decimal('garage', 12, 2)->default(0);
                $table->decimal('furniture', 12, 2)->default(0);
                $table->decimal('net_total', 12, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);            // Soll brutto
                $table->decimal('paid_amount', 12, 2)->default(0);      // Summe der zugeordneten Zahlungen (wird neu berechnet)
                $table->timestamps();

                $table->unique(['lease_id', 'period']);
                $table->index(['owner_id', 'due_date']);
            });
        }

        if (! Schema::hasTable('rent_payments')) {
            Schema::create('rent_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->date('paid_on');
                $table->decimal('amount', 12, 2);                       // negativ = Rücklastschrift oder Erstattung
                $table->string('method', 20)->default('ueberweisung');  // ueberweisung | lastschrift | bar | sonstiges
                $table->string('source', 20)->default('manual');        // manual | bank (ab Paket 5b)
                $table->unsignedBigInteger('bank_transaction_id')->nullable()->index(); // Paket 5b
                $table->string('reference', 190)->nullable();           // Verwendungszweck
                $table->text('note')->nullable();
                $table->decimal('allocated_amount', 12, 2)->default(0); // davon Mieten zugeordnet (wird neu berechnet)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['lease_id', 'paid_on']);
                $table->index(['owner_id', 'paid_on']);
            });
        }

        if (! Schema::hasTable('rent_allocations')) {
            Schema::create('rent_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rent_payment_id')->constrained('rent_payments')->cascadeOnDelete();
                $table->foreignId('rent_charge_id')->constrained('rent_charges')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->index('rent_charge_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_allocations');
        Schema::dropIfExists('rent_payments');
        Schema::dropIfExists('rent_charges');
        Schema::dropIfExists('lease_rents');
        Schema::dropIfExists('leases');
    }
};
