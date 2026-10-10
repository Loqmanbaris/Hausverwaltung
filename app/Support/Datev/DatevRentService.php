<?php

namespace App\Support\Datev;

use App\Models\DatevExport;
use App\Models\RentPayment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Money;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Auswahl der Mietzahlungen und Erzeugen des Mieten-Exports. */
class DatevRentService
{
    /**
     * @return array{bookings: array, skipped: array, already: int, config: array, problems: string[], payments: array<int, array>}
     */
    public static function plan(DateTimeImmutable $from, DateTimeImmutable $to, ?int $ownerId, bool $again): array
    {
        $config = DatevConfig::load();
        $counters = DatevRentAccounts::counterAccounts();

        $base = RentPayment::query()
            ->whereBetween('paid_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereNotIn('source', ['nk', 'co2', 'import'])   // Guthaben aus der NK-Abrechnung und Übernahme beim Import sind keine Zahlungseingänge
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId));
        $total = (clone $base)->count();
        if (! $again) {
            $base->whereNotIn('id', DB::table('datev_export_rent_items')->select('rent_payment_id'));
        }
        $rows = $base->with(['lease.unit', 'lease.tenant', 'allocations.charge'])->orderBy('paid_on')->orderBy('id')->get();

        $payments = [];
        foreach ($rows as $p) {
            $lease = $p->lease;
            $allocs = [];
            foreach ($p->allocations as $a) {
                if ($a->charge === null) {
                    continue;
                }
                $allocs[] = ['amount' => (string) $a->amount, 'charge' => $a->charge->only(array_merge(array_keys(DatevRentAccounts::COMPONENT_PART), ['vat_rate']))];
            }
            $tenant = $lease?->tenant;
            $who = $tenant ? ($tenant->type === 'firma' ? $tenant->company_name : ($tenant->last_name ?: $tenant->display_name)) : '';
            $payments[] = [
                'id' => (int) $p->id,
                'date' => $p->paid_on->format('Y-m-d'),
                'amount' => (string) $p->amount,
                'method' => (string) $p->method,
                'group' => DatevRentAccounts::group((string) ($lease?->lease_type ?? 'wohnen'), $lease?->vat_rate ?? 0),
                'label' => trim(($lease?->unit?->label ?? '').' '.$who),
                'parts' => DatevRentBookings::split((string) $p->amount, $allocs),
                'tenant' => $tenant?->display_name,
                'lease_id' => $p->lease_id,
            ];
        }

        $planned = DatevRentBookings::plan($payments, DatevRentAccounts::all(), $counters, $config['account_length']);

        $problems = array_values(array_filter(DatevConfig::problems($config), fn ($p) => ! str_starts_with($p, 'Das Gegenkonto')));
        if ($counters['bank'] === '') {
            $problems[] = 'Das Gegenkonto für Mieteinnahmen (Bank) fehlt. Bitte unter „Erlöskonten Mieten“ eintragen.';
        } elseif (! DatevConfig::validCounterAccount($counters['bank'], $config['account_length'])) {
            $problems[] = 'Das Gegenkonto für Mieteinnahmen (Bank) passt nicht zur Sachkontenlänge.';
        }

        return $planned + [
            'already' => $total - count($rows),
            'config' => $config,
            'problems' => $problems,
            'payments' => $payments,
        ];
    }

    public static function create(array $plan, DateTimeImmutable $from, DateTimeImmutable $to, ?int $ownerId, User $user): DatevExport
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        $built = DatevExporter::build($plan['config'], $plan['bookings'], $now, 'Mieten');
        $stored = DocumentStore::put($built['content']);

        try {
            return DB::transaction(function () use ($built, $stored, $plan, $from, $to, $ownerId, $user) {
                $export = DatevExport::create([
                    'kind' => 'mieten',
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
                        'counter_accounts' => DatevRentAccounts::counterAccounts(),
                        'selected_from' => $from->format('Y-m-d'),
                        'selected_to' => $to->format('Y-m-d'),
                    ]),
                ]);

                $items = [];
                foreach ($plan['bookings'] as $b) {
                    $items[] = [
                        'datev_export_id' => $export->id,
                        'rent_payment_id' => $b['payment_id'],
                        'rent_key' => $b['rent_key'],
                        'entry_date' => $b['date'],
                        'account' => $b['account'],
                        'counter' => $b['counter'],
                        'side' => $b['side'],
                        'amount' => $b['amount'],
                        'bu_key' => $b['bu_key'] !== '' ? $b['bu_key'] : null,
                        'doc' => $b['doc'],
                        'text' => mb_substr($b['text'], 0, 120),
                    ];
                }
                foreach (array_chunk($items, 200) as $chunk) {
                    DB::table('datev_export_rent_items')->insert($chunk);
                }

                Audit::log('datev.rent_exported', $export, 'DATEV-Export Mieten erstellt: '.$built['count'].' Buchungen, Saldo '.Money::format($built['sum']).', Zahlungen vom '.$from->format('d.m.Y').' bis '.$to->format('d.m.Y'));

                return $export;
            });
        } catch (Throwable $e) {
            DocumentStore::forget($stored);

            throw $e;
        }
    }
}
