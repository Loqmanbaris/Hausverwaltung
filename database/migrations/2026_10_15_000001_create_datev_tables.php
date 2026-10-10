<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('datev_accounts')) {
            Schema::create('datev_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('cost_key', 40)->unique();      // Schlüssel der Kostenart
                $table->string('account', 9)->nullable();       // Konto im Kontenrahmen
                $table->string('bu_key', 4)->nullable();        // Steuerschlüssel, nur wenn der Steuerberater einen vorgibt
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('datev_exports')) {
            Schema::create('datev_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedInteger('entry_count');
                $table->decimal('total_amount', 14, 2);
                $table->string('filename', 120);
                $table->string('storage_path', 120);            // verschlüsselt im geschützten Speicher
                $table->unsignedInteger('size_bytes');
                $table->string('sha256', 64);
                $table->text('config')->nullable();             // Einstellungen zum Zeitpunkt des Exports
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('datev_export_items')) {
            Schema::create('datev_export_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('datev_export_id')->constrained('datev_exports')->restrictOnDelete();
                $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
                $table->unique(['datev_export_id', 'receipt_id']);
                $table->index('receipt_id');
            });
        }
    }

    public function down(): void
    {
        // Bleibt bewusst erhalten, damit erzeugte Exporte nachvollziehbar bleiben.
    }
};
