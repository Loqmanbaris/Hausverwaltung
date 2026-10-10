<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 9: Abbuchungen aus dem Kontoauszug mit Belegen verknüpfen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_transaction_receipts')) {
            Schema::create('bank_transaction_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
                $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
                $table->decimal('amount', 12, 2);                 // bezahlter Betrag (positiv)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['bank_transaction_id', 'receipt_id']);
                $table->index('receipt_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_receipts');
        \Illuminate\Support\Facades\DB::table('bank_transactions')->whereIn('status', ['beleg', 'kein_beleg'])->update(['status' => 'ausgabe', 'status_note' => null]);
    }
};
