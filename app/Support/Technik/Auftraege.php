<?php

namespace App\Support\Technik;

use App\Models\HandoverProtocol;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use App\Support\Audit;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Money;
use App\Support\Pdf\HtmlPdf;
use Illuminate\Support\Str;
use RuntimeException;

/** Schadensfälle und Handwerkeraufträge (Paket 24). */
class Auftraege
{
    public const TOKEN_DAYS_AFTER_DONE = 30;

    public static function note(WorkOrder $o, string $text, ?User $user, ?string $author = null): void
    {
        WorkOrderNote::create(['work_order_id' => $o->id, 'user_id' => $user?->id, 'author' => mb_substr($author ?? ($user?->name ?? 'System'), 0, 120), 'text' => $text]);
    }

    /** Aktuellen Mietvertrag einer Einheit finden (für Kontakt und Zuordnung). */
    public static function leaseFor(?int $unitId): ?Lease
    {
        return $unitId ? Lease::query()->where('unit_id', $unitId)->activeOn(date('Y-m-d'))->with('tenant.contacts')->first() : null;
    }

    public static function create(array $data, ?User $user, string $source = 'manuell'): WorkOrder
    {
        $p = ! empty($data['property_id']) ? Property::query()->find((int) $data['property_id']) : null;
        $lease = ! empty($data['lease_id']) ? Lease::query()->find((int) $data['lease_id']) : self::leaseFor(! empty($data['unit_id']) ? (int) $data['unit_id'] : null);
        $o = WorkOrder::create([
            'owner_id' => $p?->owner_id ?? ($data['owner_id'] ?? null),
            'property_id' => $p?->id,
            'unit_id' => $data['unit_id'] ?? null,
            'lease_id' => $lease?->id,
            'maintenance_task_id' => $data['maintenance_task_id'] ?? null,
            'source' => $source,
            'inquiry_id' => $data['inquiry_id'] ?? null,
            'handover_protocol_id' => $data['handover_protocol_id'] ?? null,
            'title' => mb_substr(trim((string) $data['title']), 0, 190),
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'gemeldet',
            'vendor_id' => $data['vendor_id'] ?? null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_phone' => $data['reporter_phone'] ?? null,
            'created_by' => $user?->id,
        ]);
        self::note($o, 'Schadensfall angelegt ('.(WorkOrder::SOURCE[$source] ?? $source).').', $user);
        Audit::log('workorder.created', $o, 'Schadensfall '.$o->number().': '.$o->title.' – '.$o->place(), actor: $user);

        return $o;
    }

    /** Meldung über das Formular der Webseite als Schadensfall übernehmen. */
    public static function fromInquiry(Inquiry $q): ?WorkOrder
    {
        if ($q->topic !== 'schaden' || WorkOrder::query()->where('inquiry_id', $q->id)->exists()) {
            return null;
        }
        $msg = (string) $q->message;
        $place = '';
        if (preg_match('/^Schadensmeldung – Wohnung: (.*)$/mu', $msg, $m)) {
            $place = trim($m[1]);
        }
        // Objekt anhand der Adresse raten (gleicher Eigentümer wie in der Anfrage)
        $property = null;
        if ($place !== '') {
            $cands = Property::query()->when($q->owner_id, fn ($w) => $w->where('owner_id', $q->owner_id))->get();
            $needle = mb_strtolower($place);
            $property = $cands->first(fn ($p) => $p->street && str_contains($needle, mb_strtolower((string) preg_replace('/\s+\d.*$/', '', $p->street))));
        }

        return self::create([
            'owner_id' => $q->owner_id,
            'property_id' => $property?->id,
            'title' => 'Schadensmeldung'.($place !== '' ? ': '.Str::limit($place, 80) : ''),
            'description' => $msg,
            'inquiry_id' => $q->id,
            'reporter_name' => $q->name,
            'reporter_phone' => $q->phone,
        ], null, 'webseite');
    }

    /** Mängel aus einem Übergabeprotokoll übernehmen. @param array<int, array{room: string, item: string, note: ?string}> $items */
    public static function fromHandover(HandoverProtocol $p, array $items, ?User $user): int
    {
        $lease = $p->lease;
        $n = 0;
        foreach ($items as $it) {
            $title = $it['item'].' ('.$it['room'].')';
            if (WorkOrder::query()->where('handover_protocol_id', $p->id)->where('title', $title)->exists()) {
                continue;
            }
            self::create([
                'property_id' => $lease?->unit?->property_id, 'unit_id' => $lease?->unit_id, 'lease_id' => $lease?->id,
                'handover_protocol_id' => $p->id, 'title' => $title,
                'description' => trim('Mangel laut '.$p->title().' vom '.$p->handover_on->format('d.m.Y').'.'.(! empty($it['note']) ? "\n".$it['note'] : '')),
            ], $user, 'uebergabe');
            $n++;
        }

        return $n;
    }

    /** Neuen Zugangslink für den Handwerker erzeugen (der alte wird ungültig). */
    public static function newToken(WorkOrder $o): string
    {
        $token = Str::random(48);
        $o->update(['token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDays(120)]);

        return $token;
    }

    public static function link(string $token): string
    {
        return route('vendor.order', $token);
    }

    public static function byToken(string $token): ?WorkOrder
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }
        $o = WorkOrder::query()->where('token_hash', hash('sha256', $token))->first();
        if ($o === null || ($o->token_expires_at && $o->token_expires_at->isPast()) || $o->status === 'storniert') {
            return null;
        }

        return $o;
    }

    public static function pdf(WorkOrder $o): string
    {
        $o->loadMissing(['property', 'unit', 'lease.tenant.contacts', 'vendor', 'photos']);

        return HtmlPdf::render(view('workorders.print', ['o' => $o, 'thumbs' => Fotos::thumbs($o), 'contact' => self::tenantContact($o)])->render(), 'Auftrag '.$o->number());
    }

    /** Ansprechpartner vor Ort (Mieter) für den Handwerker. */
    public static function tenantContact(WorkOrder $o): ?array
    {
        $t = $o->lease?->tenant;
        if ($t === null) {
            return $o->reporter_name ? ['name' => $o->reporter_name, 'phone' => $o->reporter_phone] : null;
        }
        $phone = $t->phone ?: $t->contacts->pluck('phone')->filter()->first();

        return ['name' => (string) $t->display_name, 'phone' => $phone];
    }

    /** Auftrag an Handwerker vergeben, optional mit E-Mail (Link + PDF). @return array{token: string, mail: ?string} */
    public static function assign(WorkOrder $o, Vendor $v, ?string $message, bool $sendMail, ?string $estimate, ?User $user): array
    {
        if (! in_array($o->status, ['gemeldet', 'beauftragt', 'termin'], true)) {
            throw new RuntimeException('Dieser Auftrag ist schon abgeschlossen.');
        }
        $o->update(['vendor_id' => $v->id, 'status' => $o->status === 'termin' ? 'termin' : 'beauftragt', 'cost_estimate' => $estimate]);
        $token = self::newToken($o);
        $mailState = null;
        if ($sendMail) {
            if (! $v->email || ! MailService::validAddress($v->email)) {
                $mailState = 'Keine E-Mail-Adresse beim Handwerker hinterlegt – bitte Auftrag drucken oder Link selbst weitergeben.';
            } else {
                $body = "Guten Tag,\n\nwir beauftragen Sie mit folgender Arbeit:\n\n"
                    .$o->number().': '.$o->title."\nOrt: ".self::address($o)."\n"
                    .($o->priority !== 'normal' ? 'Dringlichkeit: '.WorkOrder::PRIORITY[$o->priority]."\n" : '')
                    .($o->description ? "\n".$o->description."\n" : '')
                    .($message ? "\n".$message."\n" : '')
                    .(($c = self::tenantContact($o)) ? "\nAnsprechpartner vor Ort: ".$c['name'].($c['phone'] ? ', Tel. '.$c['phone'] : '')."\n" : '')
                    ."\nBitte nennen Sie uns über diesen Link einen Termin und melden Sie dort, wenn die Arbeit erledigt ist (gerne mit Fotos):\n".self::link($token)
                    ."\n\nDie Rechnung senden Sie bitte an uns mit der Auftragsnummer ".$o->number().'.'
                    ."\n\nMit freundlichen Grüßen\n".MailSettings::fromName().(MailSettings::get('mail_signature') !== '' ? "\n\n".MailSettings::get('mail_signature') : '');
                $log = MailService::send([$v->email], 'Auftrag '.$o->number().': '.$o->title, $body, [['name' => 'auftrag-'.$o->number().'.pdf', 'data' => self::pdf($o), 'mime' => 'application/pdf']],
                    ['kind' => 'auftrag', 'ref_id' => $o->id, 'owner_id' => $o->owner_id, 'lease_id' => $o->lease_id, 'tenant_id' => $o->lease?->tenant_id], $user);
                $mailState = $log->status === 'gesendet' ? null : 'E-Mail nicht gesendet: '.$log->error;
            }
        }
        self::note($o, 'Beauftragt: '.$v->name.($estimate !== null ? ', Kostenvoranschlag '.Money::format($estimate) : '').($sendMail && $mailState === null ? ' (per E-Mail mit Link)' : '').($message ? "\n".$message : ''), $user);
        Audit::log('workorder.assigned', $o, $o->number().' an '.$v->name.' vergeben', actor: $user);

        return ['token' => $token, 'mail' => $mailState];
    }

    public static function address(WorkOrder $o): string
    {
        $p = $o->property;
        if (! $p) {
            return 'siehe Beschreibung';
        }

        return trim(($p->street ?? '').', '.trim(($p->zip ?? '').' '.($p->city ?? '')), ', ').($o->unit ? ' – '.$o->unit->label.($o->unit->floor ? ', '.$o->unit->floor : '') : '');
    }

    public static function setAppointment(WorkOrder $o, string $at, ?string $note, ?User $user, ?string $author = null): void
    {
        $o->update(['appointment_at' => $at, 'status' => in_array($o->status, ['gemeldet', 'beauftragt', 'termin'], true) ? 'termin' : $o->status]);
        self::note($o, 'Termin: '.date('d.m.Y H:i', strtotime($at)).' Uhr'.($note ? "\n".$note : ''), $user, $author);
    }

    public static function markDone(WorkOrder $o, string $doneOn, ?string $note, ?User $user, ?string $author = null): void
    {
        if (! $o->isOpen()) {
            throw new RuntimeException('Der Auftrag ist schon abgeschlossen.');
        }
        $o->update(['status' => 'erledigt', 'done_on' => $doneOn, 'token_expires_at' => now()->addDays(self::TOKEN_DAYS_AFTER_DONE)]);
        self::note($o, 'Erledigt am '.date('d.m.Y', strtotime($doneOn)).($note ? "\n".$note : ''), $user, $author);
        if ($o->maintenance_task_id && ($t = MaintenanceTask::query()->find($o->maintenance_task_id))) {
            Wartung::complete($t, $doneOn, 'über Auftrag '.$o->number(), $user, $o->id);
        }
        Audit::log('workorder.done', $o, $o->number().' erledigt'.($author ? ' (gemeldet von '.$author.')' : ''), actor: $user, label: $user ? null : $author);
    }

    public static function bill(WorkOrder $o, Receipt $r, ?User $user): void
    {
        if (! in_array($o->status, ['erledigt', 'beauftragt', 'termin', 'abgerechnet'], true)) {
            throw new RuntimeException('Erst beauftragen oder erledigt melden.');
        }
        if ((int) $r->owner_id !== (int) $o->owner_id) {
            throw new RuntimeException('Der Beleg gehört zu einem anderen Eigentümer.');
        }
        $o->update(['receipt_id' => $r->id, 'status' => 'abgerechnet', 'done_on' => $o->done_on ?? $r->receipt_date ?? now()]);
        self::note($o, 'Rechnung verknüpft: Beleg Nr. '.$r->id.($r->amount_gross !== null ? ' über '.Money::format($r->amount_gross) : ''), $user);
        Audit::log('workorder.billed', $o, $o->number().' abgerechnet mit Beleg Nr. '.$r->id, actor: $user);
    }

    public static function cancel(WorkOrder $o, ?string $reason, ?User $user): void
    {
        $o->update(['status' => 'storniert', 'token_hash' => null]);
        self::note($o, 'Storniert'.($reason ? ': '.$reason : ''), $user);
    }

    public static function reopen(WorkOrder $o, ?User $user): void
    {
        $o->update(['status' => $o->vendor_id ? 'beauftragt' : 'gemeldet', 'receipt_id' => null]);
        self::note($o, 'Wieder geöffnet', $user);
    }

    /** Hinweis an die Verwaltung bei Rückmeldung des Handwerkers. */
    public static function notifyTeam(WorkOrder $o, string $what): void
    {
        try {
            $to = MailSettings::copyAddress();
            if (! MailService::validAddress($to) || (MailService::$fake === null && ! MailSettings::configured())) {
                return;
            }
            MailService::send([$to], $o->number().': '.$what, $what."\n\n".$o->title.' – '.$o->place()."\n\nIm CRM ansehen: ".route('workorders.show', $o), [], ['kind' => 'hinweis', 'ref_id' => $o->id, 'owner_id' => $o->owner_id], null, false);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
