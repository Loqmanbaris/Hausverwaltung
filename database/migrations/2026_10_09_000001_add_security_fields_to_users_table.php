<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->default(0);
            $table->text('two_factor_recovery_codes')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'is_active', 'must_change_password',
                'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step',
                'two_factor_recovery_codes', 'failed_login_count', 'locked_until', 'last_login_at',
            ]);
        });
    }
};
