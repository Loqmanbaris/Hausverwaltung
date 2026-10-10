<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 26: CO2-Kostenaufteilung und Heizkosten-Zähler.
 *  - nk_settings: Brennstoff, Gebäudeart, Einschränkung nach § 9 CO2KostAufG, Vergleichswert für die Verbrauchsinfo
 *  - nk_settlements: CO2-Menge und CO2-Kosten laut Rechnung des Versorgers
 *  - meters: fernablesbar, Eichfrist
 *  - co2_claims: Erstattung an Mieter mit eigenem Vertrag (Etagenheizung)
 *  - consumption_notices: versendete monatliche Verbrauchsinformationen
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nk_settings', 'fuel')) {
            Schema::table('nk_settings', function (Blueprint $table) {
                $table->string('fuel', 20)->nullable()->after('consumption_share');               // erdgas | heizoel | fluessiggas | fernwaerme | kohle | waermepumpe | holz | strom | sonstiges
                $table->string('building_use', 20)->nullable()->after('fuel');                    // null = automatisch | wohn | nichtwohn
                $table->string('co2_restriction', 20)->default('keine')->after('building_use');  // keine | halb | entfall
                $table->string('co2_restriction_note', 190)->nullable()->after('co2_restriction');
                $table->decimal('benchmark_kwh', 8, 1)->nullable()->after('co2_restriction_note'); // Vergleichsnutzer kWh je m² und Jahr
            });
        }

        if (! Schema::hasColumn('nk_settlements', 'co2_kg')) {
            Schema::table('nk_settlements', function (Blueprint $table) {
                $table->decimal('co2_kg', 12, 1)->nullable()->after('note');
                $table->decimal('co2_cost', 12, 2)->nullable()->after('co2_kg');    // brutto
                $table->decimal('co2_area', 10, 2)->nullable()->after('co2_cost');  // Wohnfläche, leer = Summe der Einheiten
            });
        }

        if (! Schema::hasColumn('meters', 'remote')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->boolean('remote')->default(false)->after('factor');
                $table->unsignedSmallInteger('calibrated_until')->nullable()->after('remote'); // Jahr, Eichfrist endet am 31.12.
            });
        }

        if (! Schema::hasTable('co2_claims')) {
            Schema::create('co2_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->date('period_from');
                $table->date('period_to');
                $table->decimal('co2_kg', 12, 1);
                $table->decimal('co2_cost', 12, 2);
                $table->decimal('area', 10, 2);
                $table->boolean('gas_stove')->default(false);
                $table->string('building_use', 20)->default('wohn');
                $table->string('restriction', 20)->default('keine');
                $table->decimal('kg_per_sqm', 8, 1);
                $table->unsignedTinyInteger('step');
                $table->unsignedTinyInteger('landlord_pct');
                $table->decimal('amount', 12, 2);
                $table->date('claimed_on')->nullable();
                $table->string('status', 20)->default('offen');                     // offen | gutgeschrieben | ausgezahlt
                $table->date('settled_on')->nullable();
                $table->foreignId('rent_payment_id')->nullable()->constrained('rent_payments')->nullOnDelete();
                $table->string('note', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
            });
        }

        if (! Schema::hasTable('consumption_notices')) {
            Schema::create('consumption_notices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
                $table->date('month');                                               // erster Tag des Monats
                $table->string('method', 20)->default('email');                      // email | erledigt
                $table->unsignedInteger('count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['property_id', 'month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_notices');
        Schema::dropIfExists('co2_claims');
        if (Schema::hasColumn('meters', 'remote')) {
            Schema::table('meters', fn (Blueprint $t) => $t->dropColumn(['remote', 'calibrated_until']));
        }
        if (Schema::hasColumn('nk_settlements', 'co2_kg')) {
            Schema::table('nk_settlements', fn (Blueprint $t) => $t->dropColumn(['co2_kg', 'co2_cost', 'co2_area']));
        }
        if (Schema::hasColumn('nk_settings', 'fuel')) {
            Schema::table('nk_settings', fn (Blueprint $t) => $t->dropColumn(['fuel', 'building_use', 'co2_restriction', 'co2_restriction_note', 'benchmark_kwh']));
        }
    }
};
