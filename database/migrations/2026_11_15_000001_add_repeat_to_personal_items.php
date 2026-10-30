<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_items') && ! Schema::hasColumn('personal_items', 'repeat')) {
            Schema::table('personal_items', function (Blueprint $table) {
                $table->string('repeat', 8)->nullable();                       // weekly | monthly | nth | yearly, leer = einmalig
                $table->unsignedTinyInteger('repeat_every')->default(1);       // alle n Wochen/Monate/Jahre
                $table->date('repeat_until')->nullable();                      // Serie endet am (einschließlich)
            });
        }
        if (! Schema::hasTable('personal_item_marks')) {
            Schema::create('personal_item_marks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personal_item_id')->constrained('personal_items')->cascadeOnDelete();
                $table->date('on_date');
                $table->string('kind', 6);                                     // done | skip: einzelner Tag einer Serie
                $table->timestamps();
                $table->unique(['personal_item_id', 'on_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_item_marks');
        if (Schema::hasColumn('personal_items', 'repeat')) {
            Schema::table('personal_items', function (Blueprint $table) {
                $table->dropColumn(['repeat', 'repeat_every', 'repeat_until']);
            });
        }
    }
};
