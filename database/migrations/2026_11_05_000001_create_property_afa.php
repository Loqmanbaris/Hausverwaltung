<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 29: Gebäude-AfA je Objekt für die Anlage V. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_afa')) {
            Schema::create('property_afa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->unique()->constrained('properties')->cascadeOnDelete();
                $table->decimal('building_share', 5, 2)->nullable();      // Gebäudeanteil am Kaufpreis in Prozent
                $table->decimal('basis_override', 12, 2)->nullable();     // Bemessungsgrundlage direkt (z. B. laut Steuerberater)
                $table->decimal('extra_basis', 12, 2)->default(0);        // nachträgliche Herstellungskosten am Gebäude
                $table->decimal('rate', 4, 2)->default(2);                // AfA-Satz in Prozent
                $table->date('start_date')->nullable();                   // Anschaffung bzw. Fertigstellung
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('property_afa_years')) {
            Schema::create('property_afa_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->decimal('amount', 12, 2);                         // Jahresbetrag laut Steuerberater (ersetzt die Rechnung)
                $table->string('note', 190)->nullable();
                $table->timestamps();
                $table->unique(['property_id', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_afa_years');
        Schema::dropIfExists('property_afa');
    }
};
