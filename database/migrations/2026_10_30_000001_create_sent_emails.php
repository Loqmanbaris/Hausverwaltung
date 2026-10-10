<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket 21: E-Mail-Versand – Verlauf der gesendeten E-Mails
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sent_emails')) {
            Schema::create('sent_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();
                $table->string('kind', 20);                 // mahnung | nk | vertrag | uebergabe | raten | anfrage | rundschreiben | hinweis | test
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->string('recipients', 500);
                $table->string('subject', 200);
                $table->text('body');
                $table->text('attachments')->nullable();   // Dateinamen als JSON
                $table->string('status', 10);               // gesendet | fehler
                $table->text('error')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['kind', 'ref_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
