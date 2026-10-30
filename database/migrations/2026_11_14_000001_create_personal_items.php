<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_items')) {
            Schema::create('personal_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 8)->default('todo');            // todo | termin
                $table->string('title', 190);
                $table->text('notes')->nullable();
                $table->date('due_on');                                // Tag
                $table->string('start_time', 5)->nullable();           // HH:MM, leer = ganztägig
                $table->string('end_time', 5)->nullable();
                $table->string('remind', 8)->nullable();               // leer | morgen | 0 | 15 | 60 | 1440 (Minuten vorher)
                $table->dateTime('done_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'due_on']);
            });
        }
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'day_mail')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('day_mail')->default(false);           // „Mein Tag“ morgens per E-Mail
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_items');
        if (Schema::hasColumn('users', 'day_mail')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('day_mail');
            });
        }
    }
};
