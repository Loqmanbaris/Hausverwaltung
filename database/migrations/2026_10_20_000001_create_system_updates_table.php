<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 6: Protokoll der über die Update-Seite eingespielten Pakete. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_updates')) {
            Schema::create('system_updates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60)->index();
                $table->string('title', 190);
                $table->string('package_sha256', 64);
                $table->string('status', 20);                 // installiert | test_fehler | fehlgeschlagen | zurueckgenommen
                $table->string('backup_dir', 190)->nullable();
                $table->json('files_changed')->nullable();
                $table->json('files_new')->nullable();
                $table->json('migrations')->nullable();
                $table->string('selftest', 60)->nullable();
                $table->text('output')->nullable();
                $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
