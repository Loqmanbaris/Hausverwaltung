<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Recht „Alle E-Mails“: Mitarbeiter sieht das ganze Postfach wie ein Administrator (Wunsch Inhaber 09.11.2026: Gillian)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'mail_all')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('mail_all')->default(false);
            });
        }
        DB::table('users')->where('name', 'like', 'Gillian%')->update(['mail_all' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'mail_all')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('mail_all');
            });
        }
    }
};
