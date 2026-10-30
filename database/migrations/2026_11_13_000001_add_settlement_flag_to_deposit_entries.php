<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deposit_entries') && ! Schema::hasColumn('deposit_entries', 'settlement')) {
            Schema::table('deposit_entries', function (Blueprint $table) {
                // true = Buchung stammt aus der Abrechnung (Einbehalt, Auszahlung); nur diese werden beim Zurücknehmen entfernt
                $table->boolean('settlement')->default(false)->after('note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deposit_entries', 'settlement')) {
            Schema::table('deposit_entries', function (Blueprint $table) {
                $table->dropColumn('settlement');
            });
        }
    }
};
