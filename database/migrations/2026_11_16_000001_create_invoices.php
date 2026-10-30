<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->nullable()->unique();          // erst beim Festschreiben vergeben: fortlaufend, ohne Lücken
                $table->string('type', 3)->default('380');                   // 380 Rechnung | 381 Gutschrift
                $table->string('status', 16)->default('entwurf');            // entwurf | festgeschrieben | versendet | storniert
                $table->string('recipient_kind', 8)->default('free');        // tenant | owner | free
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->text('buyer');                                       // JSON: Name, Anschrift, E-Mail, USt-IdNr, Unternehmer ja/nein
                $table->text('seller')->nullable();                          // JSON: Stand der Hausverwaltung beim Festschreiben
                $table->string('tax_mode', 6)->default('regel');             // regel | klein (§ 19 UStG)
                $table->date('issued_on')->nullable();
                $table->date('due_on')->nullable();
                $table->date('delivery_from')->nullable();                   // Leistungsdatum oder Beginn des Leistungszeitraums
                $table->date('delivery_to')->nullable();
                $table->string('buyer_reference', 100)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('original_id')->nullable();       // bei Gutschrift: die stornierte Rechnung
                $table->decimal('net', 12, 2)->default(0);
                $table->decimal('vat', 12, 2)->default(0);
                $table->decimal('gross', 12, 2)->default(0);
                $table->longText('xml')->nullable();                         // XRechnung, nur bei Rechnungen an Unternehmen
                $table->date('sent_on')->nullable();
                $table->date('paid_on')->nullable();
                $table->dateTime('finalized_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'issued_on']);
            });
        }
        if (! Schema::hasTable('invoice_lines')) {
            Schema::create('invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->unsignedSmallInteger('pos')->default(1);
                $table->string('name', 300);
                $table->decimal('quantity', 12, 3)->default(1);
                $table->string('unit', 4)->default('C62');                   // UN/ECE-Code: C62 Stück, DAY, WEE, MON, HUR, LS
                $table->decimal('unit_net', 12, 2)->default(0);              // Einzelpreis netto in Euro
                $table->decimal('vat_rate', 5, 2)->default(19);
                $table->string('exemption', 190)->nullable();                // Grund bei 0 % (Steuerbefreiung)
                $table->timestamps();
                $table->index(['invoice_id', 'pos']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
