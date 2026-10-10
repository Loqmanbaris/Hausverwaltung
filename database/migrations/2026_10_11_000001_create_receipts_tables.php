<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receipts')) {
            Schema::create('receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
                $table->boolean('owner_level')->default(false);     // gilt für den ganzen Eigentümer, kein Objekt
                $table->date('receipt_date')->nullable();
                $table->decimal('amount_gross', 12, 2)->nullable();
                $table->string('cost_category', 40)->nullable();
                $table->string('vendor_name', 190)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'receipt_date']);
            });
        }

        if (! Schema::hasTable('receipt_files')) {
            Schema::create('receipt_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
                $table->unsignedSmallInteger('position')->default(1);
                $table->string('storage_path', 120);                 // zufälliger Name im geschützten Speicher
                $table->string('original_name', 190);
                $table->string('mime', 60);
                $table->unsignedInteger('size_bytes');
                $table->string('sha256', 64)->index();               // Prüfsumme des gespeicherten Inhalts
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_files');
        Schema::dropIfExists('receipts');
    }
};
