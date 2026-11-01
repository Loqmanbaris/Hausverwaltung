<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// E-Mail-Empfang: Posteingang, aus dem IONOS-Postfach per IMAP abgeholt
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('received_emails')) {
            Schema::create('received_emails', function (Blueprint $table) {
                $table->id();
                $table->string('folder', 60)->default('INBOX');
                $table->unsignedBigInteger('uidvalidity');
                $table->unsignedBigInteger('uid');
                $table->string('message_id', 255)->nullable();
                $table->string('in_reply_to', 255)->nullable();
                $table->text('references')->nullable();
                $table->string('from_address', 190);
                $table->string('from_name', 190)->nullable();
                $table->string('reply_to', 190)->nullable();
                $table->string('to_addresses', 1000)->nullable();
                $table->string('cc', 1000)->nullable();
                $table->string('subject', 300);
                $table->mediumText('body');
                $table->text('attachments')->nullable();   // JSON: [{name, mime, size, path}]
                $table->unsignedInteger('size')->default(0);
                $table->dateTime('received_at')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
                $table->dateTime('read_at')->nullable();
                $table->foreignId('read_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('replied_at')->nullable();
                $table->boolean('archived')->default(false);
                $table->timestamps();
                $table->unique(['folder', 'uidvalidity', 'uid']);
                $table->index('received_at');
                $table->index('from_address');
                $table->index('message_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('received_emails');
    }
};
