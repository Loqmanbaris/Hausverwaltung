<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('datev_export_items', 'doc')) {
            Schema::table('datev_export_items', function (Blueprint $table) {
                // Was zum Zeitpunkt des Exports gebucht wurde (damit die Belegliste später genau dazu passt)
                $table->string('doc', 36)->nullable()->after('receipt_id');
                $table->date('entry_date')->nullable()->after('doc');
                $table->string('account', 9)->nullable()->after('entry_date');
                $table->string('side', 1)->nullable()->after('account');
                $table->decimal('amount', 12, 2)->nullable()->after('side');
                $table->string('text', 120)->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        // Die Spalten bleiben bewusst erhalten.
    }
};
