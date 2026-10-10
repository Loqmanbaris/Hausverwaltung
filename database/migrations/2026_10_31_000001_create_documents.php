<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket 22: Dokumentenablage je Eigentümer, Objekt, Einheit, Mieter und Mietvertrag
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
                $table->string('attach_type', 12);            // owner | property | unit | tenant | lease
                $table->unsignedBigInteger('attach_id');
                $table->string('category', 30);
                $table->string('title', 190);
                $table->string('path', 80);                   // DocumentStore (verschlüsselt)
                $table->string('original_name', 190);
                $table->string('mime', 60);
                $table->unsignedInteger('size');
                $table->char('sha256', 64);
                $table->date('doc_date')->nullable();
                $table->date('expires_on')->nullable();
                $table->unsignedSmallInteger('remind_days')->default(30);
                $table->timestamp('reminded_at')->nullable();
                $table->boolean('archived')->default(false);
                $table->text('note')->nullable();
                $table->string('source', 10)->default('upload'); // upload | email | system
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['attach_type', 'attach_id']);
                $table->index(['expires_on', 'archived']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
