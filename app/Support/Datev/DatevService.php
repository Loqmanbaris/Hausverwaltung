<?php

namespace App\Support\Datev;

use App\Models\DatevExport;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Kostenarten;
use App\Support\Money;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Auswahl der Belege und Erzeugen des Exports (Datenbank, geschützter Speicher, Protokoll). */
class DatevService
{
    /**
     * Wählt die Belege des Zeitraums aus und plant die Buchungen.
     * Nur vollständige Belege werden exportiert. Schon exportierte nur, wenn $again gesetzt ist.
     *
     * @return array{bookings: array, skipped: array, incomplete: int, already: int, config: array, problems: string[], receipts: array<int, array>}
     */
    public static function plan(DateTimeImmutable $from, DateTimeImmutable $to, ?int $ownerId, bool $again): array
    {
        $config = DatevConfig::load();
        $range = [$from->format('Y-m-d'), $to->format('Y-m-d')];

        $base = Receipt::query()->whereBetween('receipts.receipt_date', $range);
        if ($ownerId) {
            $base->where('receipts.owner_id', $ownerId);
        }

        $incomplete = (clone $base)->incomplete()->count();

        $complete = (clone $base)->complete();
        $total = (clone $complete)->count();
        if (! $again) {
            $complete->whereNotIn('receipts.id', DB::table('datev_export_items')->select('receipt_id'));
        }
        $already = $total - (clone $complete)->count();

        $rows = $complete->orderBy('receipts.receipt_date')->orderBy('receipts.id')->get();

        $receipts = [];
        foreach ($rows as $r) {
            $receipts[] = [
                'id' => (int) $r->id,
                'date' => $r->receipt_date->format('Y-m-d'),
                'amount' => (string) $r->amount_gross,
                'category' => (string) $r->cost_category,
                'category_label' => Kostenarten::label($r->cost_category),
                'vendor' => $r->vendor_name,
                'invoice_number' => $r->invoice_number,
            ];
        }

        $planned = DatevBookings::plan($receipts, DatevAccounts::all(), $config);

        return $planned + [
            'incomplete' => $incomplete,
            'already' => $already,
            'config' => $config,
            'problems' => DatevConfig::problems($config),
            'receipts' => $receipts,
        ];
    }

    /**
     * Erzeugt die Datei, legt sie verschlüsselt ab und merkt sich, welche Belege enthalten sind.
     *
     * @param  array{bookings: array, config: array}  $plan  Ergebnis von plan()
     */
    public static function create(array $plan, DateTimeImmutable $from, DateTimeImmutable $to, ?int $ownerId, User $user): DatevExport
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        $built = DatevExporter::build($plan['config'], $plan['bookings'], $now);
        $stored = DocumentStore::put($built['content']);

        try {
            return DB::transaction(function () use ($built, $stored, $plan, $from, $to, $ownerId, $user) {
                $export = DatevExport::create([
                    'created_by' => $user->id,
                    'owner_id' => $ownerId ?: null,
                    'period_from' => $built['from'],
                    'period_to' => $built['to'],
                    'entry_count' => $built['count'],
                    'total_amount' => $built['sum'],
                    'filename' => $built['filename'],
                    'storage_path' => $stored,
                    'size_bytes' => strlen($built['content']),
                    'sha256' => hash('sha256', $built['content']),
                    'config' => json_encode([
                        'consultant' => $plan['config']['consultant'],
                        'client' => $plan['config']['client'],
                        'skr' => $plan['config']['skr'],
                        'counter_account' => $plan['config']['counter_account'],
                        'selected_from' => $from->format('Y-m-d'),
                        'selected_to' => $to->format('Y-m-d'),
                    ]),
                ]);

                $items = [];
                foreach ($plan['bookings'] as $b) {
                    $items[] = [
                        'datev_export_id' => $export->id,
                        'receipt_id' => $b['receipt_id'],
                        'doc' => $b['doc'],
                        'entry_date' => $b['date'],
                        'account' => $b['account'],
                        'side' => $b['side'],
                        'amount' => $b['amount'],
                        'text' => mb_substr($b['text'], 0, 120),
                    ];
                }
                foreach (array_chunk($items, 200) as $chunk) {
                    DB::table('datev_export_items')->insert($chunk);
                }

                Audit::log(
                    'datev.exported',
                    $export,
                    'DATEV-Export erstellt: '.$built['count'].' Buchungen, Saldo '.Money::format($built['sum']).', Belege vom '.$from->format('d.m.Y').' bis '.$to->format('d.m.Y'),
                    null,
                    ['datei' => $built['filename'], 'buchungen' => (string) $built['count']]
                );

                return $export;
            });
        } catch (Throwable $e) {
            DocumentStore::forget($stored);

            throw $e;
        }
    }
}
