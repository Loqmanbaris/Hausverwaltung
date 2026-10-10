<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Paket 14: Verzugszinsen und Ratenvereinbarungen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('base_interest_rates')) {
            Schema::create('base_interest_rates', function (Blueprint $table) {
                $table->id();
                $table->date('valid_from')->unique();
                $table->decimal('rate', 5, 2);                               // Basiszinssatz nach § 247 BGB in Prozent
                $table->timestamps();
            });
            // Veröffentlichte Basiszinssätze der Deutschen Bundesbank
            $rates = [
                '2016-07-01' => -0.88, '2023-01-01' => 1.62, '2023-07-01' => 3.12, '2024-01-01' => 3.62, '2024-07-01' => 3.37,
                '2025-01-01' => 2.27, '2025-07-01' => 1.27, '2026-01-01' => 1.27, '2026-07-01' => 1.52,
            ];
            foreach ($rates as $from => $rate) {
                DB::table('base_interest_rates')->insert(['valid_from' => $from, 'rate' => $rate, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (! Schema::hasTable('interest_bookings')) {
            Schema::create('interest_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->date('period_from');                                  // Zinsen für Tage nach diesem Datum …
                $table->date('period_until');                                 // … bis einschließlich diesem Datum
                $table->string('mode', 20);                                   // verbraucher | unternehmer
                $table->decimal('amount', 12, 2);
                $table->longText('details');                                  // JSON
                $table->string('status', 20)->default('gebucht');            // gebucht | storniert
                $table->foreignId('rent_charge_id')->nullable()->constrained('rent_charges')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['lease_id', 'status']);
            });
        }

        if (! Schema::hasTable('installment_plans')) {
            Schema::create('installment_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->date('agreed_on');
                $table->decimal('debt', 12, 2);                               // Rückstand bei Abschluss
                $table->string('status', 20)->default('aktiv');              // aktiv | erfuellt | geplatzt | storniert
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['lease_id', 'status']);
            });
        }
        if (! Schema::hasTable('installments')) {
            Schema::create('installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
                $table->unsignedSmallInteger('no');
                $table->date('due_date');
                $table->decimal('amount', 12, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
        if (Schema::hasTable('interest_bookings')) {
            $ids = DB::table('interest_bookings')->whereNotNull('rent_charge_id')->pluck('rent_charge_id');
            Schema::dropIfExists('interest_bookings');
            DB::table('rent_allocations')->whereIn('rent_charge_id', $ids)->delete();
            DB::table('rent_charges')->whereIn('id', $ids)->delete();
        }
        Schema::dropIfExists('base_interest_rates');
    }
};
