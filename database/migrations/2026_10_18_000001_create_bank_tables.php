<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 5b: Kontoauszug-Import (CSV der Sparkasse und ähnlicher Banken) und Zuordnung der Gutschriften zu Mietverträgen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_imports')) {
            Schema::create('bank_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('owner_bank_account_id')->constrained('owner_bank_accounts')->restrictOnDelete();
                $table->string('filename', 190);
                $table->string('format', 30);
                $table->string('storage_path', 120)->nullable();   // Originaldatei, verschlüsselt im geschützten Speicher
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->unsignedInteger('rows_total')->default(0);
                $table->unsignedInteger('rows_new')->default(0);
                $table->unsignedInteger('rows_duplicate')->default(0);
                $table->unsignedInteger('rows_skipped')->default(0);  // vorgemerkt, nicht gebucht
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bank_transactions')) {
            Schema::create('bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_import_id')->constrained('bank_imports')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('owner_bank_account_id')->constrained('owner_bank_accounts')->restrictOnDelete();
                $table->string('dedupe_key', 64);
                $table->date('booking_date');
                $table->date('value_date')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('EUR');
                $table->string('booking_text', 100)->nullable();
                $table->text('purpose')->nullable();
                $table->string('counterparty_name', 190)->nullable();
                $table->text('counterparty_iban')->nullable();          // verschlüsselt
                $table->string('counterparty_iban_hash', 64)->nullable()->index();
                $table->string('counterparty_bic', 11)->nullable();
                $table->string('end_to_end', 100)->nullable();
                $table->string('mandate_ref', 100)->nullable();
                $table->string('creditor_id', 40)->nullable();
                $table->string('status', 20)->default('offen');         // offen | teilweise | zugeordnet | ignoriert | ausgabe
                $table->string('status_note', 190)->nullable();
                $table->timestamps();

                $table->unique(['owner_bank_account_id', 'dedupe_key']);
                $table->index(['owner_id', 'status', 'booking_date']);
            });
        }

        if (! Schema::hasTable('lease_payer_accounts')) {
            Schema::create('lease_payer_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
                $table->string('iban_hash', 64);
                $table->text('iban');                                   // verschlüsselt
                $table->string('name', 190)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['lease_id', 'iban_hash']);
                $table->index('iban_hash');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_payer_accounts');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_imports');
    }
};
