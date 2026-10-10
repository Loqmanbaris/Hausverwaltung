<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_expenses')) {
            Schema::create('recurring_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
                $table->boolean('owner_level')->default(false);      // gilt für den ganzen Eigentümer
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
                $table->string('vendor_name', 190)->nullable();
                $table->string('cost_category', 40);
                $table->decimal('amount_gross', 12, 2)->nullable();   // erwarteter Betrag (nur zur Orientierung)
                $table->string('frequency', 20);                      // monthly | quarterly | half_yearly | yearly
                $table->unsignedTinyInteger('due_month')->default(1);
                $table->unsignedTinyInteger('due_day')->default(1);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->boolean('active')->default(true);
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['owner_id', 'active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
