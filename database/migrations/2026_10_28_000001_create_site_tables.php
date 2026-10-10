<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paket 16: Gestaltung der Webseite (Bilder, Objekt-Galerie, Downloads, Thema der Anfrage). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_objects')) {
            Schema::create('site_objects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->unique()->constrained('properties')->cascadeOnDelete();
                $table->boolean('visible')->default(false);
                $table->string('title', 120)->nullable();
                $table->text('text')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('site_assets')) {
            Schema::create('site_assets', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 20);                                  // logo | hero | object | download
                $table->unsignedBigInteger('ref_id')->nullable();             // site_objects.id bei kind=object
                $table->string('title', 190)->nullable();
                $table->string('file', 120);
                $table->string('mime', 60);
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->index(['kind', 'ref_id']);
            });
        }
        if (Schema::hasTable('inquiries') && ! Schema::hasColumn('inquiries', 'topic')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->string('topic', 20)->nullable()->after('owner_id');  // null = Anfrage | schaden
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inquiries', 'topic')) {
            Schema::table('inquiries', fn (Blueprint $t) => $t->dropColumn('topic'));
        }
        Schema::dropIfExists('site_assets');
        Schema::dropIfExists('site_objects');
    }
};
