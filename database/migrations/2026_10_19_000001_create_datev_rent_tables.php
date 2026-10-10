<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 5c: Mieteinnahmen im DATEV-Export. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('datev_rent_accounts')) {
            Schema::create('datev_rent_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('rent_key', 40)->unique();      // z. B. gewerbe_19:miete
                $table->string('account', 9)->nullable();
                $table->string('bu_key', 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('datev_exports', 'kind')) {
            Schema::table('datev_exports', function (Blueprint $table) {
                $table->string('kind', 20)->default('belege')->after('id');   // belege | mieten
            });
        }

        if (! Schema::hasTable('datev_export_rent_items')) {
            Schema::create('datev_export_rent_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('datev_export_id')->constrained('datev_exports')->restrictOnDelete();
                $table->unsignedBigInteger('rent_payment_id')->index();       // Zahlung (bleibt auch nach Storno nachvollziehbar)
                $table->string('rent_key', 40);
                $table->date('entry_date');
                $table->string('account', 9);
                $table->string('counter', 10);
                $table->string('side', 1);
                $table->decimal('amount', 12, 2);
                $table->string('bu_key', 4)->nullable();
                $table->string('doc', 36);
                $table->string('text', 120);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('datev_export_rent_items');
        if (Schema::hasColumn('datev_exports', 'kind')) {
            \Illuminate\Support\Facades\DB::table('datev_exports')->where('kind', 'mieten')->delete();
            Schema::table('datev_exports', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
        Schema::dropIfExists('datev_rent_accounts');
    }
};
