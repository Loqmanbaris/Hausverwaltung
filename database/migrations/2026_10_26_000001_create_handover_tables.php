<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 13: Übergabeprotokoll bei Ein- und Auszug. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('handover_protocols')) {
            Schema::create('handover_protocols', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('kind', 10);                                  // einzug | auszug
                $table->date('handover_on');
                $table->string('status', 20)->default('entwurf');           // entwurf | abgeschlossen
                $table->string('attendees', 500)->nullable();
                $table->longText('rooms');                                   // JSON
                $table->longText('keys');                                    // JSON
                $table->text('note')->nullable();
                $table->string('new_address', 300)->nullable();             // Auszug: neue Anschrift des Mieters
                $table->foreignId('compare_id')->nullable()->constrained('handover_protocols')->nullOnDelete();
                $table->string('sign_method', 20)->nullable();              // tablet | papier
                $table->timestamp('signed_at')->nullable();
                $table->string('tenant_signer', 190)->nullable();
                $table->string('landlord_signer', 190)->nullable();
                $table->string('tenant_signature', 120)->nullable();
                $table->string('landlord_signature', 120)->nullable();
                $table->char('content_hash', 64)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['lease_id', 'kind']);
            });
        }
        if (! Schema::hasTable('handover_photos')) {
            Schema::create('handover_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('handover_protocol_id')->constrained('handover_protocols')->cascadeOnDelete();
                $table->unsignedSmallInteger('room');                        // Index des Raums
                $table->string('path', 120);                                 // DocumentStore (groß)
                $table->string('thumb', 120);                                // DocumentStore (klein)
                $table->string('caption', 190)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('handover_readings')) {
            Schema::create('handover_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('handover_protocol_id')->constrained('handover_protocols')->cascadeOnDelete();
                $table->foreignId('meter_id')->constrained('meters')->restrictOnDelete();
                $table->decimal('value', 14, 3)->nullable();
                $table->foreignId('meter_reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
                $table->timestamps();
                $table->unique(['handover_protocol_id', 'meter_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_readings');
        Schema::dropIfExists('handover_photos');
        Schema::dropIfExists('handover_protocols');
    }
};
