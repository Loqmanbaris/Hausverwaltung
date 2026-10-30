<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 31: Mietinteressenten, Besichtigungen und Selbstauskunft. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applicants')) {
            Schema::create('applicants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->unsignedBigInteger('listing_id')->nullable();
                $table->unsignedBigInteger('inquiry_id')->nullable()->unique();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100);
                $table->string('email', 190)->nullable();
                $table->string('phone', 60)->nullable();
                $table->unsignedTinyInteger('persons')->nullable();
                $table->date('move_in')->nullable();
                $table->string('source', 20)->default('manuell');      // webseite | portal | empfehlung | manuell
                $table->text('message')->nullable();
                $table->string('status', 20)->default('neu');          // neu | besichtigung | selbstauskunft | zusage | absage | vertrag | zurueckgezogen
                $table->text('note')->nullable();
                $table->string('reason', 190)->nullable();             // interner Grund bei Absage
                $table->date('decided_on')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->date('delete_after')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['owner_id', 'status']);
                $table->index('delete_after');
            });
        }
        if (! Schema::hasTable('applicant_viewings')) {
            Schema::create('applicant_viewings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->dateTime('starts_at');
                $table->string('place', 190)->nullable();
                $table->string('status', 20)->default('geplant');      // geplant | stattgefunden | abgesagt | nicht_erschienen
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('starts_at');
            });
        }
        if (! Schema::hasTable('applicant_disclosures')) {
            Schema::create('applicant_disclosures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('applicant_id')->unique()->constrained('applicants')->cascadeOnDelete();
                $table->string('status', 20)->default('angefordert');  // angefordert | ausgefuellt | manuell
                $table->string('token_hash', 64)->nullable()->index();
                $table->text('token_enc')->nullable();                 // Link-Schlüssel verschlüsselt, damit der Link für die E-Mail wieder gebaut werden kann
                $table->dateTime('token_expires_at')->nullable();
                $table->dateTime('requested_at')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('consent_at')->nullable();
                $table->longText('data_enc')->nullable();              // verschlüsseltes JSON
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_disclosures');
        Schema::dropIfExists('applicant_viewings');
        Schema::dropIfExists('applicants');
    }
};
