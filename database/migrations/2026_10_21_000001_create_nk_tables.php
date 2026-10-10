<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 7: Nebenkostenabrechnung. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nk_settings')) {
            Schema::create('nk_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->unique()->constrained('properties')->restrictOnDelete();
                $table->string('heating_mode', 20)->default('flaeche');          // zaehler | flaeche | direkt
                $table->unsignedTinyInteger('consumption_share')->default(70);   // Anteil nach Verbrauch in Prozent (50 bis 70)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nk_cost_keys')) {
            Schema::create('nk_cost_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
                $table->string('cost_key', 40);
                $table->string('key_type', 20);                                   // flaeche | personen | wasser | einheiten | heizung | warmwasser | nicht
                $table->decimal('commercial_percent', 5, 2)->nullable();          // Vorwegabzug Gewerbe
                $table->timestamps();
                $table->unique(['property_id', 'cost_key']);
            });
        }

        if (! Schema::hasTable('lease_persons')) {
            Schema::create('lease_persons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
                $table->date('valid_from');
                $table->unsignedTinyInteger('persons');
                $table->timestamps();
                $table->unique(['lease_id', 'valid_from']);
            });
        }

        if (! Schema::hasTable('meters')) {
            Schema::create('meters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
                $table->string('kind', 20);                                       // wasser | warmwasser | waerme | hkv
                $table->string('label', 100)->nullable();                         // z. B. Bad, Küche, Heizkörper Wohnzimmer
                $table->string('number', 60)->nullable();
                $table->decimal('factor', 8, 4)->default(1);                      // Umrechnungsfaktor (Heizkostenverteiler)
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('meter_readings')) {
            Schema::create('meter_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meter_id')->constrained('meters')->cascadeOnDelete();
                $table->date('read_on');
                $table->decimal('value', 14, 3);
                $table->string('note', 190)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['meter_id', 'read_on']);
            });
        }

        if (! Schema::hasTable('nk_settlements')) {
            Schema::create('nk_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->date('period_from');
                $table->date('period_to');
                $table->string('status', 20)->default('entwurf');                 // entwurf | abgeschlossen
                $table->longText('result')->nullable();                           // Ergebnis zum Zeitpunkt des Abschlusses (JSON)
                $table->date('issued_on')->nullable();                            // Datum der Abrechnung
                $table->text('note')->nullable();                                 // Text, der auf jeder Abrechnung steht
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
                $table->index(['property_id', 'period_from']);
            });
        }

        if (! Schema::hasTable('nk_settlement_costs')) {
            Schema::create('nk_settlement_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nk_settlement_id')->constrained('nk_settlements')->cascadeOnDelete();
                $table->foreignId('receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
                $table->string('cost_key', 40);
                $table->decimal('amount', 12, 2);
                $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();  // nur diese Einheit
                $table->string('label', 190)->nullable();
                $table->boolean('included')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('rent_charges', 'kind')) {
            Schema::table('rent_charges', function (Blueprint $table) {
                $table->string('kind', 20)->default('miete')->after('owner_id');     // miete | nk
                $table->foreignId('nk_settlement_id')->nullable()->after('kind')->constrained('nk_settlements')->restrictOnDelete();
                $table->string('label', 190)->nullable()->after('nk_settlement_id');
            });
            Schema::table('rent_charges', function (Blueprint $table) {
                $table->unique(['lease_id', 'period', 'kind'], 'rent_charges_lease_period_kind_unique');
            });
            Schema::table('rent_charges', function (Blueprint $table) {
                $table->dropUnique('rent_charges_lease_id_period_unique');
            });
        }

        if (! Schema::hasColumn('rent_payments', 'nk_settlement_id')) {
            Schema::table('rent_payments', function (Blueprint $table) {
                $table->foreignId('nk_settlement_id')->nullable()->after('bank_transaction_id')->constrained('nk_settlements')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rent_payments', 'nk_settlement_id')) {
            \Illuminate\Support\Facades\DB::table('rent_payments')->whereNotNull('nk_settlement_id')->delete();
            Schema::table('rent_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('nk_settlement_id');
            });
        }
        if (Schema::hasColumn('rent_charges', 'kind')) {
            \Illuminate\Support\Facades\DB::table('rent_allocations')->whereIn('rent_charge_id', \Illuminate\Support\Facades\DB::table('rent_charges')->where('kind', '!=', 'miete')->select('id'))->delete();
            \Illuminate\Support\Facades\DB::table('rent_charges')->where('kind', '!=', 'miete')->delete();
            Schema::table('rent_charges', function (Blueprint $table) {
                $table->unique(['lease_id', 'period']);
            });
            Schema::table('rent_charges', function (Blueprint $table) {
                $table->dropUnique('rent_charges_lease_period_kind_unique');
                $table->dropConstrainedForeignId('nk_settlement_id');
                $table->dropColumn(['kind', 'label']);
            });
        }
        Schema::dropIfExists('nk_settlement_costs');
        Schema::dropIfExists('nk_settlements');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('lease_persons');
        Schema::dropIfExists('nk_cost_keys');
        Schema::dropIfExists('nk_settings');
    }
};
