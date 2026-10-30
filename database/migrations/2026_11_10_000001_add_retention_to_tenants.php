<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 35: Löschfristen für ehemalige Mieter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'retention_hold')) {
                $table->boolean('retention_hold')->default(false);
                $table->string('hold_reason', 190)->nullable();
                $table->dateTime('contact_cleaned_at')->nullable();
                $table->dateTime('erased_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['retention_hold', 'hold_reason', 'contact_cleaned_at', 'erased_at']);
        });
    }
};
