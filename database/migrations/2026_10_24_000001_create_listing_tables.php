<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 11: Webseite, Inserate, Belegung der Montage-Wohnungen, Anfragen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listings')) {
            Schema::create('listings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->string('kind', 20);                                  // wohnen | montage | gewerbe
                $table->string('status', 20)->default('entwurf');            // entwurf | online | offline
                $table->string('slug', 120)->unique();
                $table->string('title', 120);
                $table->text('description')->nullable();                     // Objektbeschreibung
                $table->text('equipment_text')->nullable();                  // Ausstattung
                $table->text('location_text')->nullable();                   // Lage
                $table->text('other_text')->nullable();                      // Sonstiges
                $table->text('features')->nullable();                        // JSON-Liste der Merkmale
                $table->boolean('show_address')->default(false);
                $table->date('available_from')->nullable();
                $table->decimal('area_sqm', 8, 2)->nullable();
                $table->decimal('rooms', 4, 1)->nullable();
                $table->string('floor', 30)->nullable();
                $table->unsignedSmallInteger('year_built')->nullable();
                // Wohnen und Gewerbe
                $table->decimal('rent_cold', 12, 2)->nullable();
                $table->decimal('service_charge', 12, 2)->nullable();
                $table->decimal('heating_charge', 12, 2)->nullable();
                $table->decimal('deposit', 12, 2)->nullable();
                $table->boolean('plus_vat')->default(false);
                // Montage
                $table->decimal('price_night', 10, 2)->nullable();
                $table->decimal('price_week', 10, 2)->nullable();
                $table->decimal('price_month', 10, 2)->nullable();
                $table->boolean('price_per_person')->default(false);
                $table->unsignedTinyInteger('beds')->nullable();
                $table->unsignedSmallInteger('min_nights')->nullable();
                // Energieausweis (Pflichtangaben in Anzeigen, § 87 GEG)
                $table->string('energy_type', 20)->nullable();               // verbrauch | bedarf | keiner
                $table->decimal('energy_value', 7, 1)->nullable();
                $table->string('energy_source', 60)->nullable();
                $table->unsignedSmallInteger('energy_year')->nullable();
                $table->string('energy_class', 4)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'kind']);
            });
        }

        if (! Schema::hasTable('listing_photos')) {
            Schema::create('listing_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
                $table->string('file', 120);                                  // Ordner/Name ohne Endung
                $table->string('caption', 190)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('unit_bookings')) {
            Schema::create('unit_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->date('date_from');
                $table->date('date_to');                                       // letzte Nacht ist date_to - 1 (Abreisetag)
                $table->string('status', 20)->default('belegt');               // reserviert | belegt | gesperrt
                $table->string('label', 190)->nullable();                      // Firma oder Gast (nicht öffentlich)
                $table->unsignedTinyInteger('persons')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('inquiry_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['unit_id', 'date_from']);
            });
        }

        if (! Schema::hasTable('inquiries')) {
            Schema::create('inquiries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->string('name', 120);
                $table->string('company', 120)->nullable();
                $table->string('email', 190);
                $table->string('phone', 60)->nullable();
                $table->unsignedTinyInteger('persons')->nullable();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->text('message');
                $table->string('status', 20)->default('neu');                  // neu | in_bearbeitung | erledigt
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('unit_bookings');
        Schema::dropIfExists('listing_photos');
        Schema::dropIfExists('listings');
    }
};
