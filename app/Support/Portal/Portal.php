<?php

namespace App\Support\Portal;

use App\Models\Document;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\TenantPortalUser;
use App\Models\User;
use App\Support\Audit;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Mail\Notify;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mieterportal (Paket 36). Anmeldung ohne Passwort: Der Mieter fordert einen Link an, der 20 Minuten gilt und einmal benutzt werden kann.
 * Der Mieter sieht nur Daten seiner eigenen Mietverträge.
 */
class Portal
{
    public const TOKEN_MINUTES = 20;

    public const SESSION_MINUTES = 120;

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    // ------------------------------------------------------------------ Zugang

    public static function enable(Tenant $t, string $email, User $by): TenantPortalUser
    {
        $email = self::normalize($email);
        if (! MailService::validAddress($email)) {
            throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eintragen.');
        }
        $u = TenantPortalUser::query()->firstOrNew(['tenant_id' => $t->id]);
        $u->fill(['email' => $email, 'enabled' => true, 'created_by' => $u->created_by ?? $by->id])->save();
        Audit::log('portal.enabled', $t, 'Mieterportal freigegeben: '.$t->display_name);

        return $u;
    }

    public static function disable(Tenant $t): void
    {
        $u = TenantPortalUser::query()->where('tenant_id', $t->id)->first();
        if ($u) {
            $u->update(['enabled' => false]);
            DB::table('tenant_portal_tokens')->where('portal_user_id', $u->id)->delete();
            Audit::log('portal.disabled', $t, 'Mieterportal gesperrt: '.$t->display_name);
        }
    }

    /** Erzeugt einen Anmeldelink und schickt ihn per E-Mail. Der Link selbst wird nicht im E-Mail-Verlauf gespeichert. */
    public static function sendLink(TenantPortalUser $u, bool $invite = false): void
    {
        if (MailService::$fake === null && ! MailSettings::configured()) {
            throw new RuntimeException('Die E-Mail ist noch nicht eingerichtet (Verwaltung → E-Mail einrichten).');
        }
        $token = Str::random(48);
        DB::table('tenant_portal_tokens')->where('portal_user_id', $u->id)->where(fn ($q) => $q->whereNotNull('used_at')->orWhere('expires_at', '<', now()))->delete();
        DB::table('tenant_portal_tokens')->insert(['portal_user_id' => $u->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(self::TOKEN_MINUTES), 'created_at' => now(), 'updated_at' => now()]);
        $t = $u->tenant;
        $name = MailSettings::fromName();
        $body = "Guten Tag,\n\n".($invite ? "Sie können jetzt unser Mieterportal nutzen: Dort sehen Sie Ihr Mieterkonto und Ihre Dokumente, melden Zählerstände und Schäden.\n\n" : '')
            ."Mit diesem Link melden Sie sich an:\n".route('portal.confirm', $token)."\n\nDer Link gilt ".self::TOKEN_MINUTES." Minuten und kann einmal verwendet werden. Wenn Sie ihn nicht angefordert haben, ignorieren Sie diese E-Mail.\n\nMit freundlichen Grüßen\n".$name;
        $log = MailService::send([$u->email], $invite ? 'Ihr Zugang zum Mieterportal' : 'Anmeldung im Mieterportal', $body, [], ['kind' => 'hinweis', 'tenant_id' => $t?->id, 'owner_id' => $t?->owner_id], null, false);
        $log->update(['body' => 'Anmeldelink für das Mieterportal (der Link wird aus Sicherheitsgründen nicht gespeichert).']);
        if ($log->status !== 'gesendet') {
            throw new RuntimeException('Die E-Mail konnte nicht gesendet werden.');
        }
        if ($invite) {
            $u->update(['invited_at' => now()]);
        }
    }

    /** Mieter fordert einen Link an. Antwortet nie, ob die Adresse bekannt ist. */
    public static function requestLogin(string $email): void
    {
        $u = TenantPortalUser::query()->where('email', self::normalize($email))->where('enabled', true)->with('tenant')->first();
        if ($u === null || $u->tenant === null) {
            return;
        }
        try {
            self::sendLink($u);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Gültiger, unbenutzter Link? */
    public static function peek(string $token): ?TenantPortalUser
    {
        if (preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            return null;
        }
        $row = DB::table('tenant_portal_tokens')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->first();
        if ($row === null) {
            return null;
        }
        $u = TenantPortalUser::query()->whereKey($row->portal_user_id)->where('enabled', true)->with('tenant')->first();

        return $u?->tenant ? $u : null;
    }

    /** Benutzt den Link: Er gilt danach nicht mehr. */
    public static function consume(string $token): ?TenantPortalUser
    {
        $u = self::peek($token);
        if ($u === null) {
            return null;
        }
        $n = DB::table('tenant_portal_tokens')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->update(['used_at' => now()]);
        if ($n !== 1) {
            return null;
        }
        $u->update(['last_login_at' => now()]);
        Audit::log('portal.login', $u->tenant, 'Mieter im Portal angemeldet: '.$u->tenant->display_name, null, null, 'Mieter');

        return $u;
    }

    // ------------------------------------------------------------------ Daten des Mieters

    public static function leases(Tenant $t): Collection
    {
        return Lease::query()->where('tenant_id', $t->id)->with('unit.property')->orderByDesc('start_date')->get();
    }

    /** @return array<int, array{lease: Lease, rows: array, open: float, payments: Collection}> */
    public static function account(Tenant $t, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $out = [];
        foreach (self::leases($t) as $l) {
            $charges = RentCharge::query()->where('lease_id', $l->id)->where('due_date', '<=', $today)->orderByDesc('due_date')->limit(36)->get();
            $rows = $charges->map(fn ($c) => ['label' => $c->kind === 'miete' ? $c->period->format('m/Y') : ucfirst((string) $c->kind), 'due' => $c->due_date, 'total' => (float) $c->total, 'paid' => min((float) $c->paid_amount, (float) $c->total), 'open' => $c->openAmount()])->all();
            $open = (float) RentCharge::query()->where('lease_id', $l->id)->where('due_date', '<=', $today)->get()->sum(fn ($c) => $c->openAmount());
            $payments = RentPayment::query()->where('lease_id', $l->id)->whereNotIn('source', ['import', 'nk', 'co2'])->orderByDesc('paid_on')->limit(24)->get();
            $out[] = ['lease' => $l, 'rows' => $rows, 'open' => round($open, 2), 'payments' => $payments];
        }

        return $out;
    }

    public static function documents(Tenant $t): Collection
    {
        $leaseIds = Lease::query()->where('tenant_id', $t->id)->pluck('id')->all();

        return Document::query()->where('shared_with_tenant', true)->where('archived', false)
            ->where(function ($q) use ($t, $leaseIds) {
                $q->where(fn ($x) => $x->where('attach_type', 'tenant')->where('attach_id', $t->id));
                if ($leaseIds !== []) {
                    $q->orWhere(fn ($x) => $x->where('attach_type', 'lease')->whereIn('attach_id', $leaseIds));
                }
            })->orderByDesc('doc_date')->orderByDesc('id')->get();
    }

    public static function ownDocument(Tenant $t, int $id): ?Document
    {
        return self::documents($t)->firstWhere('id', $id);
    }

    /** Zähler der Wohnungen, in denen der Mieter wohnt. */
    public static function meters(Tenant $t, ?string $today = null): Collection
    {
        $today ??= date('Y-m-d');
        $unitIds = Lease::query()->where('tenant_id', $t->id)->where('start_date', '<=', $today)->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))->pluck('unit_id')->all();
        if ($unitIds === []) {
            return collect();
        }

        return Meter::query()->where('active', true)->whereIn('unit_id', $unitIds)->with('unit')->orderBy('kind')->get()->each(function ($m) {
            $m->last = $m->readings()->orderByDesc('read_on')->first();
        });
    }

    /** „1.234,567“ oder „123,4“ oder „1234.5“ */
    public static function parseNumber(string $raw): ?string
    {
        $v = trim(str_replace(' ', '', $raw));
        if ($v === '') {
            return null;
        }
        $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : (preg_match('/^\d{1,3}(\.\d{3})+$/', $v) === 1 ? str_replace('.', '', $v) : $v);

        return preg_match('/^\d{1,9}(\.\d{1,3})?$/', $v) === 1 ? $v : null;
    }

    public static function addReading(Tenant $t, int $meterId, string $on, string $rawValue): MeterReading
    {
        $meter = self::meters($t)->firstWhere('id', $meterId);
        if ($meter === null) {
            throw new RuntimeException('Dieser Zähler gehört nicht zu Ihrer Wohnung.');
        }
        $value = self::parseNumber($rawValue);
        if ($value === null) {
            throw new RuntimeException('Bitte den Zählerstand als Zahl eingeben, zum Beispiel 1234,5.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) !== 1 || $on > date('Y-m-d') || $on < date('Y-m-d', strtotime('-60 days'))) {
            throw new RuntimeException('Das Datum ist ungültig. Es darf höchstens 60 Tage zurückliegen und nicht in der Zukunft liegen.');
        }
        $last = $meter->last;
        if ($last !== null && (float) $value < (float) $last->value) {
            throw new RuntimeException('Der Stand ist kleiner als der letzte gemeldete ('.number_format((float) $last->value, 3, ',', '.').'). Bitte prüfen Sie die Eingabe.');
        }
        if (MeterReading::query()->where('meter_id', $meter->id)->whereDate('read_on', $on)->exists()) {
            throw new RuntimeException('Für diesen Tag gibt es schon einen Stand. Bitte melden Sie sich bei uns, wenn er falsch ist.');
        }
        $r = MeterReading::create(['meter_id' => $meter->id, 'read_on' => $on, 'value' => $value, 'note' => 'Mieterportal']);
        Audit::log('portal.reading', $t, 'Zählerstand über das Mieterportal gemeldet: '.$t->display_name.', '.$value, null, null, 'Mieter');

        return $r;
    }

    public static function reportDamage(TenantPortalUser $pu, Lease $lease, string $title, string $text): Inquiry
    {
        $t = $pu->tenant;
        abort_unless($lease->tenant_id === $t->id, 404);
        $lease->loadMissing('unit.property');
        $addr = trim(($lease->unit?->property?->street ?? '').', '.($lease->unit?->label ?? ''), ', ');
        $q = Inquiry::create([
            'owner_id' => $lease->owner_id, 'topic' => 'schaden', 'name' => $t->display_name, 'email' => $pu->email, 'phone' => $t->phone,
            'message' => 'Adresse und Wohnung: '.$addr."\n\n".trim($title)."\n".trim($text)."\n\n(Gemeldet über das Mieterportal)", 'status' => 'neu',
        ]);
        Audit::log('portal.damage', $t, 'Schaden über das Mieterportal gemeldet: '.$t->display_name, null, null, 'Mieter');
        Notify::newInquiry($q);

        return $q;
    }
}
