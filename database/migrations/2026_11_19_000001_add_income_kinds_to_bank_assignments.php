<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gutschrift aufteilen wie bei Objego: Einnahmeart und Monat je Zahlung, dazu Posten ohne Mietzahlung (Kaution, Sonstiges)
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rent_payments') && ! Schema::hasColumn('rent_payments', 'income_kind')) {
            Schema::table('rent_payments', function (Blueprint $table) {
                $table->string('income_kind', 20)->default('miete')->after('source');   // miete | nk | gebuehr
                $table->date('for_period')->nullable()->after('income_kind');            // Monat, auf den die Miete gebucht wird (leer = ältester offener)
            });
        }
        if (! Schema::hasTable('bank_transaction_items')) {
            Schema::create('bank_transaction_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_transaction_id')->index();
                $table->foreignId('owner_id')->index();
                $table->foreignId('lease_id')->nullable()->index();
                $table->string('kind', 20);                         // kaution | sonstiges
                $table->decimal('amount', 12, 2);
                $table->string('note', 190)->nullable();
                $table->foreignId('deposit_entry_id')->nullable();  // Buchung bei der Kaution
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_items');
        if (Schema::hasColumn('rent_payments', 'income_kind')) {
            Schema::table('rent_payments', function (Blueprint $table) {
                $table->dropColumn(['income_kind', 'for_period']);
            });
        }
    }
};
