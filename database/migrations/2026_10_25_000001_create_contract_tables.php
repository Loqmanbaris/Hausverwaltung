<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 12: Mietverträge erstellen, Vorlagen, Unterschrift. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 20)->unique();                       // wohnen | gewerbe | montage
                $table->longText('sections');                                // JSON
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lease_contracts')) {
            Schema::create('lease_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('kind', 20);
                $table->string('status', 20)->default('entwurf');          // entwurf | unterschrieben | storniert
                $table->longText('extras');                                 // Zusatzangaben (JSON)
                $table->longText('header');                                 // Vertragsparteien und Objekt (JSON)
                $table->longText('body');                                   // Paragrafen (JSON)
                $table->char('content_hash', 64);
                $table->string('sign_method', 20)->nullable();             // tablet | papier
                $table->string('sign_place', 120)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('tenant_signer', 190)->nullable();
                $table->string('landlord_signer', 190)->nullable();
                $table->string('tenant_signature', 120)->nullable();       // DocumentStore (PNG, verschlüsselt)
                $table->string('landlord_signature', 120)->nullable();
                $table->string('scan_path', 120)->nullable();              // DocumentStore
                $table->string('scan_name', 190)->nullable();
                $table->string('scan_mime', 60)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['lease_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_contracts');
        Schema::dropIfExists('contract_templates');
    }
};
