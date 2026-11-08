<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mitteilungen aufs Handy (Web-Push) und Wochenüberblick am Montag 07:30
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('endpoint_hash', 64)->unique();
                $table->text('endpoint');
                $table->string('public_key');
                $table->string('auth_token');
                $table->string('content_encoding', 20)->default('aes128gcm');
                $table->string('device', 190)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
        // Was schon geschickt wurde (Erinnerung je Eintrag und Tag, Wochenüberblick je Woche), damit nichts doppelt kommt
        if (! Schema::hasTable('tag_notifications')) {
            Schema::create('tag_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('key', 120);
                $table->timestamp('sent_at');
                $table->unique(['user_id', 'key']);
            });
        }
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'week_mail')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('week_mail')->default(true); // Wochenüberblick montags 07:30 per E-Mail
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_notifications');
        Schema::dropIfExists('push_subscriptions');
        if (Schema::hasColumn('users', 'week_mail')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('week_mail');
            });
        }
    }
};
