<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 37: E-Rechnungen (XRechnung) für umsatzsteuerpflichtige Vermietung an Unternehmen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('issued_invoices')) {
            Schema::create('issued_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->unsignedBigInteger('rent_charge_id')->nullable()->unique();   // Soll-Posten, aus dem die Rechnung entstand
                $table->unsignedBigInteger('original_id')->nullable();                // bei Gutschrift: die stornierte Rechnung
                $table->string('type', 3)->default('380');                            // 380 Rechnung | 381 Gutschrift
                $table->string('number', 40);
                $table->date('issued_on');
                $table->date('due_on');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->decimal('net', 12, 2);
                $table->decimal('vat', 12, 2);
                $table->decimal('gross', 12, 2);
                $table->decimal('vat_rate', 5, 2);
                $table->string('status', 12)->default('erstellt');                    // erstellt | versendet | storniert
                $table->date('sent_on')->nullable();
                $table->longText('xml');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['owner_id', 'number']);
                $table->index(['owner_id', 'issued_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_invoices');
    }
};
