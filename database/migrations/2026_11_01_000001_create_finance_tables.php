<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket 23: Finanzierung – Darlehen, Ereignisse (Zahlungen, Sondertilgungen, Zinsänderungen), Werte je Objekt
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loans')) {
            Schema::create('loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->string('name', 120);
                $table->string('lender', 120)->nullable();
                $table->string('account_no', 60)->nullable();
                $table->text('lender_iban')->nullable();               // verschlüsselt
                $table->string('lender_iban_hash', 64)->nullable()->index();
                $table->string('kind', 12);                           // annuitaet | tilgung | endfaellig | privat
                $table->decimal('principal', 12, 2);
                $table->date('paid_out_on')->nullable();
                $table->decimal('interest_rate', 6, 3);
                $table->decimal('installment', 12, 2)->nullable();     // Rate (Annuität/Privat) bzw. Tilgungsbetrag je Zahlung
                $table->decimal('initial_repayment', 6, 3)->nullable(); // anfängliche Tilgung in % (Annuität)
                $table->unsignedTinyInteger('interval_months')->default(1);
                $table->date('first_payment_on');
                $table->date('fixed_until')->nullable();
                $table->date('free_until')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('special_repayment_pct', 5, 2)->nullable();
                $table->string('status', 10)->default('aktiv');      // aktiv | getilgt
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('loan_events')) {
            Schema::create('loan_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->string('type', 14);                           // zahlung | sondertilgung | zinsaenderung | saldo | jahreszinsen
                $table->date('date');
                $table->decimal('amount', 12, 2)->nullable();
                $table->decimal('new_rate', 6, 3)->nullable();
                $table->decimal('new_installment', 12, 2)->nullable();
                $table->foreignId('bank_transaction_id')->nullable()->unique()->constrained('bank_transactions')->nullOnDelete();
                $table->string('note', 190)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['loan_id', 'date']);
            });
        }
        if (! Schema::hasTable('property_values')) {
            Schema::create('property_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->unique()->constrained('properties')->cascadeOnDelete();
                $table->decimal('purchase_price', 12, 2)->nullable();
                $table->decimal('purchase_costs', 12, 2)->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('equity', 12, 2)->nullable();
                $table->decimal('market_value', 12, 2)->nullable();
                $table->date('market_value_date')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_events');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('property_values');
    }
};
