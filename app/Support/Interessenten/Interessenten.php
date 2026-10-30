<?php

namespace App\Support\Interessenten;

use App\Models\Applicant;
use App\Models\ApplicantDisclosure;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mietinteressenten: Anfragen übernehmen, Status, Selbstauskunft (Link und von Hand), Mieter anlegen, Löschfristen (Paket 31).
 * Datensparsam: Die Selbstauskunft fragt nur, was für die Entscheidung nötig ist. Alles ist freiwillig.
 */
class Interessenten
{
    /** Löschfristen in Monaten: offene Verfahren, Absage und Zurückgezogen, nach Vertrag. */
    public const KEEP_OPEN = 6;

    public const KEEP_DECIDED = 3;

    public const TOKEN_DAYS = 14;

    /** Felder der Selbstauskunft: Schlüssel => [Bezeichnung, Art, Hinweis]. */
    public const FIELDS = [
        'adults' => ['Erwachsene, die einziehen', 'number', ''],
        'children' => ['Kinder, die einziehen', 'number', ''],
        'occupation' => ['Beruf oder Tätigkeit', 'text', 'z. B. Angestellter, Selbstständig, Rentner, Student'],
        'employer' => ['Arbeitgeber', 'text', 'Freiwillig'],
        'net_income' => ['Monatliches Nettoeinkommen aller Einziehenden (€)', 'money', 'Zusammen, nach Steuern. Ein Nachweis wird erst bei der Zusage verlangt.'],
        'pets' => ['Haustiere', 'text', 'Art und Anzahl, oder leer lassen'],
        'move_in' => ['Gewünschter Einzug', 'date', ''],
        'credit_ok' => ['Eine Bonitätsauskunft (z. B. SCHUFA) kann vorgelegt werden', 'choice', ''],
        'remark' => ['Anmerkung', 'textarea', 'Alles, was Sie uns noch mitteilen möchten'],
    ];

    public const CREDIT = ['unbekannt' => 'Keine Angabe', 'ja' => 'Ja', 'nein' => 'Nein'];

    // ------------------------------------------------------------------ Anlegen

    /** „Max Mustermann“ wird zu Vorname „Max“ und Nachname „Mustermann“. */
    public static function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $pos = strrpos($name, ' ');

        return $pos === false ? [null, $name] : [substr($name, 0, $pos), substr($name, $pos + 1)];
    }

    /** Übernimmt eine Anfrage von der Webseite. Ohne erkennbaren Eigentümer wird eine RuntimeException geworfen. */
    public static function fromInquiry(Inquiry $q, User $user): Applicant
    {
        if ($q->topic === 'schaden') {
            throw new RuntimeException('Eine Schadensmeldung ist keine Interessenten-Anfrage.');
        }
        $existing = Applicant::query()->where('inquiry_id', $q->id)->first();
        if ($existing) {
            return $existing;
        }
        $listing = $q->listing_id ? Listing::query()->with('unit.property')->find($q->listing_id) : null;
        $ownerId = $q->owner_id ?? $listing?->unit?->property?->owner_id;
        if ($ownerId === null) {
            $owners = Owner::query()->pluck('id');
            $ownerId = $owners->count() === 1 ? $owners->first() : null;
        }
        if ($ownerId === null) {
            throw new RuntimeException('Zu dieser Anfrage gehört kein Eigentümer. Bitte den Interessenten von Hand anlegen.');
        }
        [$first, $last] = self::splitName((string) $q->name);

        return DB::transaction(function () use ($q, $listing, $ownerId, $first, $last, $user) {
            $a = Applicant::create([
                'owner_id' => $ownerId, 'unit_id' => $listing?->unit_id, 'listing_id' => $q->listing_id, 'inquiry_id' => $q->id,
                'first_name' => $first, 'last_name' => $last, 'email' => $q->email ?: null, 'phone' => $q->phone ?: null,
                'persons' => $q->persons, 'move_in' => $q->date_from, 'source' => 'webseite', 'message' => $q->message,
                'status' => 'neu', 'created_by' => $user->id,
            ]);
            self::touch($a);
            if ($q->status === 'neu') {
                $q->update(['status' => 'in_bearbeitung']);
            }
            Audit::log('applicant.created', $a, 'Interessent aus Webseiten-Anfrage übernommen: '.$a->name(), null, ['anfrage' => $q->id]);

            return $a;
        });
    }

    // ------------------------------------------------------------------ Status und Löschfrist

    /** Setzt die Löschfrist nach dem Stand des Verfahrens neu. */
    public static function touch(Applicant $a): void
    {
        $months = in_array($a->status, ['absage', 'zurueckgezogen', 'vertrag'], true) ? self::KEEP_DECIDED : self::KEEP_OPEN;
        $a->forceFill(['delete_after' => date('Y-m-d', strtotime('+'.$months.' months'))])->save();
    }

    public static function setStatus(Applicant $a, string $status, ?string $reason = null): Applicant
    {
        if (! array_key_exists($status, Applicant::STATUS)) {
            throw new RuntimeException('Unbekannter Stand.');
        }
        $a->status = $status;
        if (in_array($status, ['absage', 'zurueckgezogen', 'zusage', 'vertrag'], true)) {
            $a->decided_on = $a->decided_on ?? date('Y-m-d');
        } else {
            $a->decided_on = null;
        }
        if ($status === 'absage' && $reason !== null) {
            $a->reason = mb_substr(trim($reason), 0, 190) ?: null;
        }
        $a->save();
        self::touch($a);
        Audit::log('applicant.status', $a, 'Interessent '.$a->name().': Stand '.$a->statusLabel());

        return $a;
    }

    /** Erst weiter, wenn es nicht schon weiter ist (Neu → Besichtigung → Selbstauskunft). */
    public static function advance(Applicant $a, string $to): void
    {
        $order = ['neu' => 0, 'besichtigung' => 1, 'selbstauskunft' => 2];
        if (isset($order[$a->status], $order[$to]) && $order[$to] > $order[$a->status]) {
            self::setStatus($a, $to);
        }
    }

    public static function purge(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $n = 0;
        foreach (Applicant::query()->whereNotNull('delete_after')->where('delete_after', '<', $today)->get() as $a) {
            // Wer noch auf eine Entscheidung wartet, wird nicht stillschweigend gelöscht, sondern bekommt neue Frist, wenn ein Termin ansteht.
            if ($a->isOpen() && $a->viewings()->where('status', 'geplant')->where('starts_at', '>=', $today)->exists()) {
                self::touch($a);

                continue;
            }
            Audit::log('applicant.purged', $a, 'Interessent nach Ablauf der Löschfrist gelöscht (Nr. '.$a->id.')');
            $a->delete();
            $n++;
        }

        return $n;
    }

    // ------------------------------------------------------------------ Selbstauskunft

    /** Legt einen neuen Link an (der alte wird ungültig). Gibt Auskunft und Klartext-Token zurück. */
    public static function requestDisclosure(Applicant $a): array
    {
        $d = ApplicantDisclosure::query()->firstOrNew(['applicant_id' => $a->id]);
        if ($d->exists && $d->isFilled()) {
            throw new RuntimeException('Die Selbstauskunft liegt schon vor.');
        }
        // Ein noch gültiger Link wird weiterverwendet, damit ein schon versendeter Link nicht ungültig wird.
        $token = self::currentToken($d);
        if ($token === null) {
            $token = Str::random(48);
            $d->fill(['status' => 'angefordert', 'token_hash' => hash('sha256', $token), 'token_enc' => Crypt::encryptString($token), 'token_expires_at' => now()->addDays(self::TOKEN_DAYS), 'requested_at' => now()])->save();
        }
        self::advance($a, 'selbstauskunft');

        return [$d, $token];
    }

    /** Der Klartext-Token, solange er gültig ist. */
    public static function currentToken(ApplicantDisclosure $d): ?string
    {
        if (! $d->exists || $d->status !== 'angefordert' || (string) $d->token_enc === '' || $d->token_expires_at === null || $d->token_expires_at->isPast()) {
            return null;
        }
        try {
            $t = Crypt::decryptString($d->token_enc);
        } catch (\Throwable) {
            return null;
        }

        return hash('sha256', $t) === $d->token_hash ? $t : null;
    }

    /** Erneuert den Link (zum Beispiel nach Ablauf). Der alte Link wird ungültig. */
    public static function renewDisclosure(Applicant $a): array
    {
        $d = ApplicantDisclosure::query()->where('applicant_id', $a->id)->first();
        if ($d !== null && ! $d->isFilled()) {
            $d->fill(['token_hash' => null, 'token_enc' => null])->save();
        }

        return self::requestDisclosure($a);
    }

    public static function link(string $token): string
    {
        return route('disclosure.form', $token);
    }

    public static function byToken(string $token): ?ApplicantDisclosure
    {
        if (preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            return null;
        }
        $d = ApplicantDisclosure::query()->where('token_hash', hash('sha256', $token))->with('applicant')->first();
        if ($d === null || $d->applicant === null || $d->status !== 'angefordert' || $d->token_expires_at === null || $d->token_expires_at->isPast()) {
            return null;
        }

        return $d;
    }

    /**
     * Prüft die Eingaben der Selbstauskunft.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function clean(array $in): array
    {
        $data = [];
        $err = [];
        foreach (['adults' => [1, 10], 'children' => [0, 10]] as $k => [$min, $max]) {
            $raw = trim((string) ($in[$k] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (! ctype_digit($raw) || (int) $raw < $min || (int) $raw > $max) {
                $err[$k] = self::FIELDS[$k][0].': bitte eine Zahl von '.$min.' bis '.$max.' angeben.';
            } else {
                $data[$k] = (int) $raw;
            }
        }
        foreach (['occupation' => 100, 'employer' => 150, 'pets' => 150, 'remark' => 1500] as $k => $max) {
            $v = trim((string) ($in[$k] ?? ''));
            if (mb_strlen($v) > $max) {
                $err[$k] = self::FIELDS[$k][0].' ist zu lang (höchstens '.$max.' Zeichen).';
            } elseif ($v !== '') {
                $data[$k] = $v;
            }
        }
        $inc = trim((string) ($in['net_income'] ?? ''));
        if ($inc !== '') {
            $m = Money::parse($inc);
            if ($m === null || (float) $m < 0 || (float) $m > 1000000) {
                $err['net_income'] = 'Nettoeinkommen: bitte einen Betrag wie 2.800 oder 2800,50 angeben.';
            } else {
                $data['net_income'] = $m;
            }
        }
        $mv = trim((string) ($in['move_in'] ?? ''));
        if ($mv !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $mv) !== 1 || ! checkdate((int) substr($mv, 5, 2), (int) substr($mv, 8, 2), (int) substr($mv, 0, 4))) {
                $err['move_in'] = 'Gewünschter Einzug: bitte ein gültiges Datum angeben.';
            } else {
                $data['move_in'] = $mv;
            }
        }
        $credit = (string) ($in['credit_ok'] ?? 'unbekannt');
        $data['credit_ok'] = array_key_exists($credit, self::CREDIT) ? $credit : 'unbekannt';

        return [$data, $err];
    }

    /** Speichert die Selbstauskunft. $how = ausgefuellt (Interessent online) oder manuell (von Hand eingetragen). */
    public static function submit(Applicant $a, array $data, string $how, bool $consent): ApplicantDisclosure
    {
        $d = ApplicantDisclosure::query()->firstOrNew(['applicant_id' => $a->id]);
        $d->setData($data);
        $d->fill(['status' => $how, 'submitted_at' => now(), 'consent_at' => $consent ? now() : null, 'token_hash' => null, 'token_enc' => null, 'token_expires_at' => null])->save();
        self::advance($a, 'selbstauskunft');
        if ($how === 'manuell' && $d->requested_at === null) {
            $d->update(['requested_at' => now()]);
        }

        return $d;
    }

    /** Verhältnis Nettoeinkommen zur Kaltmiete des Inserats, nur als Orientierung. */
    public static function incomeRatio(Applicant $a): ?array
    {
        $d = $a->disclosure;
        $income = $d && $d->isFilled() ? (float) ($d->data()['net_income'] ?? 0) : 0.0;
        $listing = $a->listing ?? ($a->unit_id ? Listing::query()->where('unit_id', $a->unit_id)->whereNotNull('rent_cold')->orderByDesc('id')->first() : null);
        $rent = (float) ($listing?->rent_cold ?? 0);
        if ($income <= 0 || $rent <= 0) {
            return null;
        }

        return ['income' => $income, 'rent' => $rent, 'factor' => round($income / $rent, 1)];
    }

    // ------------------------------------------------------------------ Mieter anlegen

    /** Legt aus dem Interessenten einen Mieter an (oder nimmt den schon angelegten) und setzt den Stand auf „Vertrag“. */
    public static function makeTenant(Applicant $a, User $user): Tenant
    {
        if ($a->tenant_id !== null) {
            $t = Tenant::query()->find($a->tenant_id);
            if ($t !== null) {
                return $t;
            }
        }

        return DB::transaction(function () use ($a, $user) {
            $data = ['type' => 'person', 'first_name' => $a->first_name, 'last_name' => $a->last_name];
            $t = Tenant::create([
                'owner_id' => $a->owner_id, 'type' => 'person', 'first_name' => $a->first_name, 'last_name' => $a->last_name,
                'display_name' => Tenant::composeName($data), 'email' => $a->email, 'phone' => $a->phone,
            ]);
            $a->tenant_id = $t->id;
            $a->save();
            self::setStatus($a, 'vertrag');
            Audit::log('tenant.created', $t, 'Mieter aus Interessent angelegt: '.$t->display_name, null, ['interessent' => $a->id]);

            return $t;
        });
    }
}
