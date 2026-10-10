<?php

use App\Models\Vendor;
use App\Support\Kostenarten;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_types')) {
            Schema::create('cost_types', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->string('label', 120);
                $table->string('group_label', 80);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->boolean('allocatable')->default(false);   // für die Nebenkostenabrechnung umlagefähig (Vorschlag)
                $table->boolean('is_system')->default(false);     // aus der Startliste, der Schlüssel bleibt fest
                $table->timestamps();
            });
        }

        if (DB::table('cost_types')->count() === 0) {
            $sort = 0;
            $first = true;
            foreach (Kostenarten::GROUPS as $group => $items) {
                foreach ($items as $key => $label) {
                    $sort += 10;
                    DB::table('cost_types')->insert([
                        'key' => $key,
                        'label' => $label,
                        'group_label' => $group,
                        'sort' => $sort,
                        'active' => true,
                        'allocatable' => $first,
                        'is_system' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $first = false;
            }
        }

        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 190);
                $table->string('name_key', 190)->unique();        // Name in Kleinbuchstaben zum Wiederfinden
                $table->string('street', 190)->nullable();
                $table->string('zip', 10)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('vat_id', 20)->nullable();
                $table->string('default_cost_category', 40)->nullable();
                $table->text('note')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('receipts', 'vendor_id')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->foreignId('vendor_id')->nullable()->after('cost_category')->constrained('vendors')->restrictOnDelete();
            });
        }

        // Lieferanten aus bereits erfassten Belegen übernehmen
        $names = DB::table('receipts')->whereNull('vendor_id')->whereNotNull('vendor_name')->distinct()->pluck('vendor_name');
        foreach ($names as $name) {
            $key = Vendor::normalizeName($name);
            if ($key === '') {
                continue;
            }
            $existing = DB::table('vendors')->where('name_key', $key)->first();
            $id = $existing
                ? $existing->id
                : DB::table('vendors')->insertGetId([
                    'name' => mb_substr(trim($name), 0, 190),
                    'name_key' => $key,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('receipts')->whereNull('vendor_id')->where('vendor_name', $name)->update(['vendor_id' => $id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('receipts', 'vendor_id')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_id');
            });
        }
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('cost_types');
    }
};
