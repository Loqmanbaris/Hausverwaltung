<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('receipts', 'source')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->string('invoice_number', 80)->nullable()->after('vendor_name');
                $table->date('due_date')->nullable()->after('receipt_date');
                $table->decimal('net_amount', 12, 2)->nullable()->after('amount_gross');
                $table->decimal('tax_amount', 12, 2)->nullable()->after('net_amount');
                $table->string('source', 20)->default('manual')->after('note');        // manual oder e_invoice
                $table->string('einvoice_format', 40)->nullable()->after('source');   // z. B. XRechnung
                $table->index(['vendor_id', 'invoice_number']);
            });
        }
    }

    public function down(): void
    {
        // Die Spalten bleiben bewusst erhalten, damit keine Angaben aus Belegen verloren gehen.
    }
};
