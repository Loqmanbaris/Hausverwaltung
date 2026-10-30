<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 36: Mieterportal (Anmeldung per Link, Konto, Dokumente, Zählerstände, Schadensmeldung). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_portal_users')) {
            Schema::create('tenant_portal_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                $table->string('email', 190)->index();
                $table->boolean('enabled')->default(true);
                $table->dateTime('invited_at')->nullable();
                $table->dateTime('last_login_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tenant_portal_tokens')) {
            Schema::create('tenant_portal_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('portal_user_id')->constrained('tenant_portal_users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->dateTime('expires_at');
                $table->dateTime('used_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('documents', 'shared_with_tenant')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->boolean('shared_with_tenant')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_portal_tokens');
        Schema::dropIfExists('tenant_portal_users');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('shared_with_tenant');
        });
    }
};
