<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Outlook-Ansicht: HTML-Fassung der empfangenen E-Mail (NULL = noch nicht geholt, '' = keine HTML-Fassung)
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('received_emails') && ! Schema::hasColumn('received_emails', 'body_html')) {
            Schema::table('received_emails', function (Blueprint $table) {
                $table->mediumText('body_html')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('received_emails', 'body_html')) {
            Schema::table('received_emails', function (Blueprint $table) {
                $table->dropColumn('body_html');
            });
        }
    }
};
