<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 32: Laufende Verträge (Strom, Gas, Versicherung, Hausmeister ...) mit Kündigungserinnerung und Kosten. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_contracts')) {
            Schema::create('service_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->unsignedBigInteger('property_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->string('kind', 30);                                  // strom | gas | wasser | ...
                $table->string('title', 150);
                $table->string('provider', 150)->nullable();                 // Anbieter
                $table->string('contract_no', 80)->nullable();
                $table->string('customer_no', 80)->nullable();
                $table->string('meter_no', 80)->nullable();
                $table->date('start_date')->nullable();
                $table->date('term_end')->nullable();                        // Laufzeitende bzw. früheste Kündigung zum
                $table->boolean('auto_renew')->default(false);
                $table->unsignedSmallInteger('renew_months')->default(12);
                $table->unsignedSmallInteger('notice_value')->default(3);
                $table->string('notice_unit', 10)->default('monate');        // tage | wochen | monate
                $table->unsignedSmallInteger('remind_days')->default(30);
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('interval', 20)->default('monatlich');        // monatlich | vierteljaehrlich | halbjaehrlich | jaehrlich | einmalig
                $table->unsignedBigInteger('cost_type_id')->nullable();
                $table->string('status', 15)->default('aktiv');              // aktiv | gekuendigt | beendet
                $table->date('cancelled_on')->nullable();
                $table->date('cancel_effective')->nullable();
                $table->date('reminded_for')->nullable();                    // Termin, für den zuletzt erinnert wurde
                $table->unsignedTinyInteger('reminded_stage')->default(0);
                $table->string('contact', 190)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_contracts');
    }
};
