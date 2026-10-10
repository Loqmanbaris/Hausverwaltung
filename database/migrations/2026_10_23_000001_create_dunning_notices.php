<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 10: Mahnwesen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dunning_notices')) {
            Schema::create('dunning_notices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->unsignedTinyInteger('level');                      // 1 Zahlungserinnerung, 2 Mahnung, 3 letzte Mahnung
                $table->date('issued_on');
                $table->date('pay_by');
                $table->decimal('open_amount', 12, 2);                     // offener Betrag (ohne neue Gebühr)
                $table->decimal('fee', 12, 2)->default(0);
                $table->longText('items');                                 // offene Posten zum Zeitpunkt der Mahnung (JSON)
                $table->text('note')->nullable();
                $table->string('status', 20)->default('erstellt');         // erstellt | storniert
                $table->foreignId('fee_charge_id')->nullable()->constrained('rent_charges')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['lease_id', 'issued_on']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dunning_notices')) {
            $ids = \Illuminate\Support\Facades\DB::table('dunning_notices')->whereNotNull('fee_charge_id')->pluck('fee_charge_id');
            Schema::dropIfExists('dunning_notices');
            \Illuminate\Support\Facades\DB::table('rent_allocations')->whereIn('rent_charge_id', $ids)->delete();
            \Illuminate\Support\Facades\DB::table('rent_charges')->whereIn('id', $ids)->delete();
        }
    }
};
