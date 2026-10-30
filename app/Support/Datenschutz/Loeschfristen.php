<?php

namespace App\Support\Datenschutz;

use App\Models\Deposit;
use App\Models\Document;
use App\Models\InstallmentPlan;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\SentEmail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ablage\Ablage;
use App\Support\Audit;
use App\Support\Kaution\Kaution;
use App\Support\Rent\Erhoehung\Regeln;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Löschfristen für ehemalige Mieter (Paket 35). Das CRM löscht nie von allein, es schlägt vor und fragt.
 * Stufe 1 (Standard 36 Monate nach Mietende, Verjährung): Kontaktdaten, Ausweis- und Bonitätsunterlagen, Fotos, Schriftverkehr, E-Mail-Inhalte.
 * Stufe 2 (Standard 10 Jahre): auch Name, Anschrift und übrige Unterlagen. Mieterkonto, Beträge und Abrechnungen bleiben als Buchführungsunterlagen.
 * Gesperrt: laufender Vertrag, offene Posten, nicht abgerechnete Kaution, aktive Ratenvereinbarung, oder von Hand als „aufbewahren“ markiert.
 */
class Loeschfristen
{
    public const DEFAULT_MONTHS = 36;

    public const DEFAULT_YEARS = 10;

    /** Dokumentarten, die schon in Stufe 1 entfallen. */
    public const STAGE1_CATEGORIES = ['ausweis', 'bonitaet', 'foto', 'schriftverkehr'];

    public static function months(): int
    {
        return max(1, min(120, (int) (Settings::get('retention_contact_months', self::DEFAULT_MONTHS) ?: self::DEFAULT_MONTHS)));
    }

    public static function years(): int
    {
        return max(1, min(30, (int) (Settings::get('retention_full_years', self::DEFAULT_YEARS) ?: self::DEFAULT_YEARS)));
    }

    public static function saveSettings(int $months, int $years): void
    {
        Settings::putMany(['retention_contact_months' => (string) $months, 'retention_full_years' => (string) $years]);
    }

    /** Bezugsdatum: Ende des letzten Mietvertrags, sonst letzte Änderung des Mieters. Null, wenn noch ein Vertrag läuft. */
    public static function reference(Tenant $t, string $today): ?string
    {
        $leases = Lease::withTrashed()->where('tenant_id', $t->id)->get();
        if ($leases->isEmpty()) {
            return $t->updated_at?->format('Y-m-d');
        }
        $latest = null;
        foreach ($leases as $l) {
            if ($l->end_date === null || $l->end_date->format('Y-m-d') >= $today) {
                if ($l->deleted_at === null) {
                    return null;
                }
            }
            $e = $l->end_date?->format('Y-m-d') ?? $l->deleted_at?->format('Y-m-d');
            $latest = $latest === null || $e > $latest ? $e : $latest;
        }

        return $latest;
    }

    /**
     * Stand eines Mieters.
     *
     * @return array{tenant: Tenant, ref: ?string, stage: ?int, blocked: array<int, string>, counts: array<string, int>, due1: ?string, due2: ?string}
     */
    public static function plan(Tenant $t, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $ref = self::reference($t, $today);
        $leaseIds = Lease::withTrashed()->where('tenant_id', $t->id)->pluck('id')->all();
        $blocked = [];
        if ($ref === null) {
            $blocked[] = 'Es läuft noch ein Mietvertrag.';
        }
        if ($t->retention_hold) {
            $blocked[] = 'Von Hand als „aufbewahren“ markiert'.($t->hold_reason ? ': '.$t->hold_reason : '').'.';
        }
        if ($leaseIds !== []) {
            $open = RentCharge::query()->whereIn('lease_id', $leaseIds)->whereColumn('paid_amount', '<', 'total')->get()->sum(fn ($c) => $c->openAmount());
            if ($open > 0.004) {
                $blocked[] = 'Es gibt noch offene Posten ('.number_format($open, 2, ',', '.').' €).';
            }
            if (Schema::hasTable('deposits')) {
                foreach (Deposit::query()->whereIn('lease_id', $leaseIds)->get() as $d) {
                    $st = Kaution::state($d->lease, $d, $today);
                    if (! $st['settled'] && ($st['held'] > 0 || $st['agreed'] > $st['paid'])) {
                        $blocked[] = 'Die Kaution ist noch nicht abgerechnet.';
                        break;
                    }
                }
            }
            if (Schema::hasTable('installment_plans') && InstallmentPlan::query()->whereIn('lease_id', $leaseIds)->where('status', 'aktiv')->exists()) {
                $blocked[] = 'Eine Ratenvereinbarung läuft noch.';
            }
        }
        $due1 = $ref ? Regeln::addMonths($ref, self::months()) : null;
        $due2 = $ref ? Regeln::addMonths($ref, self::years() * 12) : null;
        $stage = null;
        if ($due2 !== null && $due2 <= $today && $t->erased_at === null) {
            $stage = 2;
        } elseif ($due1 !== null && $due1 <= $today && $t->contact_cleaned_at === null) {
            $stage = 1;
        }

        return ['tenant' => $t, 'ref' => $ref, 'stage' => $stage, 'blocked' => array_values(array_unique($blocked)), 'counts' => self::counts($t, $leaseIds), 'due1' => $due1, 'due2' => $due2];
    }

    /** Was wäre betroffen? */
    private static function counts(Tenant $t, array $leaseIds): array
    {
        $docs = self::documents($t, $leaseIds)->get();

        return [
            'contacts' => (int) $t->contacts()->count(),
            'docs1' => $docs->whereIn('category', self::STAGE1_CATEGORIES)->count(),
            'docs2' => $docs->whereNotIn('category', self::STAGE1_CATEGORIES)->count(),
            'emails' => Schema::hasTable('sent_emails') ? (int) SentEmail::query()->where('tenant_id', $t->id)->where('body', '!=', '[gelöscht]')->count() : 0,
        ];
    }

    private static function documents(Tenant $t, array $leaseIds)
    {
        return Document::query()->where(function ($q) use ($t, $leaseIds) {
            $q->where(fn ($x) => $x->where('attach_type', 'tenant')->where('attach_id', $t->id));
            if ($leaseIds !== []) {
                $q->orWhere(fn ($x) => $x->where('attach_type', 'lease')->whereIn('attach_id', $leaseIds));
            }
        });
    }

    /**
     * Mieter, bei denen eine Frist abgelaufen ist (auch gesperrte, damit man sieht, woran es liegt).
     *
     * @return array<int, array>
     */
    public static function due(User $user, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $out = [];
        foreach (Tenant::query()->visibleTo($user)->whereNull('erased_at')->get() as $t) {
            $p = self::plan($t, $today);
            if ($p['stage'] !== null) {
                $out[] = $p;
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['ref'], (string) $b['ref']));

        return $out;
    }

    // ------------------------------------------------------------------ Bereinigen

    /** Führt Stufe 1 (und bei $stage 2 zusätzlich Stufe 2) aus. Gesperrte Mieter werden nicht angefasst. */
    public static function clean(Tenant $t, int $stage, User $user): array
    {
        if (! $user->isAdmin()) {
            throw new RuntimeException('Das kann nur der Administrator.');
        }
        $p = self::plan($t);
        if ($p['blocked'] !== []) {
            throw new RuntimeException('Gesperrt: '.implode(' ', $p['blocked']));
        }
        if ($p['stage'] === null || $stage > $p['stage']) {
            throw new RuntimeException('Für diesen Mieter ist diese Stufe noch nicht fällig.');
        }
        $leaseIds = Lease::withTrashed()->where('tenant_id', $t->id)->pluck('id')->all();
        $done = ['docs' => 0, 'contacts' => 0, 'emails' => 0];

        DB::transaction(function () use ($t, $stage, $leaseIds, &$done, $user) {
            if ($t->contact_cleaned_at === null) {
                foreach (self::documents($t, $leaseIds)->whereIn('category', self::STAGE1_CATEGORIES)->get() as $doc) {
                    Ablage::delete($doc);
                    $done['docs']++;
                }
                $done['contacts'] = $t->contacts()->count();
                $t->contacts()->withTrashed()->forceDelete();
                if (Schema::hasTable('sent_emails')) {
                    $done['emails'] = SentEmail::query()->where('tenant_id', $t->id)->update(['recipients' => '[gelöscht]', 'body' => '[gelöscht]', 'attachments' => null]);
                }
                $t->forceFill(['email' => null, 'phone' => null, 'notes' => null, 'contact_cleaned_at' => now()])->save();
                Audit::log('retention.stage1', $t, 'Löschfrist Stufe 1: Kontaktdaten und Unterlagen eines ehemaligen Mieters gelöscht (Mieter Nr. '.$t->id.')', null, $done);
            }
            if ($stage >= 2 && $t->erased_at === null) {
                foreach (self::documents($t, $leaseIds)->get() as $doc) {
                    Ablage::delete($doc);
                    $done['docs']++;
                }
                $isFirm = $t->type === 'firma';
                $t->forceFill([
                    'display_name' => $isFirm ? $t->display_name : 'Ehemaliger Mieter Nr. '.$t->id,
                    'first_name' => $isFirm ? $t->first_name : null, 'last_name' => $isFirm ? $t->last_name : null,
                    'street' => $isFirm ? $t->street : null, 'zip' => $isFirm ? $t->zip : null, 'city' => $isFirm ? $t->city : null, 'vat_id' => null,
                    'erased_at' => now(),
                ])->save();
                Audit::log('retention.stage2', $t, 'Löschfrist Stufe 2: Name, Anschrift und übrige Unterlagen eines ehemaligen Mieters entfernt (Mieter Nr. '.$t->id.')', null, $done);
            }
        });

        return $done;
    }

    public static function hold(Tenant $t, bool $on, ?string $reason, User $user): void
    {
        $t->forceFill(['retention_hold' => $on, 'hold_reason' => $on ? mb_substr(trim((string) $reason), 0, 190) ?: null : null])->save();
        Audit::log('retention.hold', $t, $on ? 'Mieter von der Löschung ausgenommen: '.$t->display_name : 'Aufbewahrungs-Markierung entfernt: '.$t->display_name);
    }
}
