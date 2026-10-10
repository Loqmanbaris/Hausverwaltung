<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket 24: Wartung und Prüfpflichten, Schadensfälle und Handwerkeraufträge
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_tasks')) {
            Schema::create('maintenance_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('type', 20);
                $table->string('title', 150);
                $table->unsignedSmallInteger('interval_months')->nullable();   // null = einmalig
                $table->date('next_due');
                $table->date('last_done')->nullable();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->unsignedSmallInteger('remind_days')->default(30);
                $table->timestamp('reminded_at')->nullable();
                $table->boolean('active')->default(true);
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['active', 'next_due']);
            });
        }
        if (! Schema::hasTable('maintenance_logs')) {
            Schema::create('maintenance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_task_id')->constrained('maintenance_tasks')->cascadeOnDelete();
                $table->date('done_on');
                $table->unsignedBigInteger('work_order_id')->nullable();
                $table->string('note', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('work_orders')) {
            Schema::create('work_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();
                $table->foreignId('maintenance_task_id')->nullable()->constrained('maintenance_tasks')->nullOnDelete();
                $table->string('source', 12)->default('manuell');               // manuell | webseite | uebergabe | wartung
                $table->unsignedBigInteger('inquiry_id')->nullable();
                $table->unsignedBigInteger('handover_protocol_id')->nullable();
                $table->string('title', 190);
                $table->text('description')->nullable();
                $table->string('priority', 10)->default('normal');             // normal | dringend | notfall
                $table->string('status', 12)->default('gemeldet');            // gemeldet | beauftragt | termin | erledigt | abgerechnet | storniert
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->dateTime('appointment_at')->nullable();
                $table->date('done_on')->nullable();
                $table->decimal('cost_estimate', 12, 2)->nullable();
                $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
                $table->char('token_hash', 64)->nullable()->unique();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('reporter_name', 120)->nullable();
                $table->string('reporter_phone', 60)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'property_id']);
            });
        }
        if (! Schema::hasTable('work_order_photos')) {
            Schema::create('work_order_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->string('path', 80);
                $table->string('thumb', 80);
                $table->string('by', 12)->default('team');                     // team | handwerker | mieter
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('work_order_notes')) {
            Schema::create('work_order_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('author', 120);
                $table->text('text');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_notes');
        Schema::dropIfExists('work_order_photos');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenance_tasks');
    }
};
