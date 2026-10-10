<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('owners')) {
            Schema::create('owners', function (Blueprint $table) {
                $table->id();
                $table->string('name', 190);
                $table->string('street', 190)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('tax_number')->nullable();          // verschlüsselt
                $table->string('vat_id', 20)->nullable();
                $table->string('tax_status', 10)->default('regel'); // regel | klein
                $table->string('creditor_id', 40)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('owner_bank_accounts')) {
            Schema::create('owner_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('label', 100)->nullable();
                $table->string('account_holder', 190);
                $table->text('iban');                            // verschlüsselt
                $table->string('bic', 11)->nullable();
                $table->string('bank_name', 190)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('owner_user')) {
            Schema::create('owner_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['owner_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('name', 190);
                $table->string('street', 190)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('heating_type', 30)->nullable();
                $table->string('hot_water_type', 20)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
                $table->string('label', 100);
                $table->string('floor', 30)->nullable();
                $table->string('usage_type', 30)->default('wohnen');
                $table->decimal('area_sqm', 8, 2)->nullable();
                $table->decimal('rooms', 4, 1)->nullable();
                $table->text('equipment')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('type', 10)->default('person');   // person | firma
                $table->string('display_name', 190)->index();
                $table->string('company_name', 190)->nullable();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('vat_id', 20)->nullable();
                $table->string('street', 190)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('tenant_contacts')) {
            Schema::create('tenant_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->string('name', 190);
                $table->string('role', 100)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 60)->nullable();
                $table->boolean('receives_invoices')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_contacts');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('units');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('owner_user');
        Schema::dropIfExists('owner_bank_accounts');
        Schema::dropIfExists('owners');
    }
};
