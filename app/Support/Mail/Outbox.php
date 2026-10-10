<?php

namespace App\Support\Mail;

use App\Http\Controllers\HandoverController;
use App\Models\DunningNotice;
use App\Models\HandoverProtocol;
use App\Models\Inquiry;
use App\Models\InstallmentPlan;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\NkSettlement;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Contracts\ContractService;
use App\Support\Dunning\Mahnwesen;
use App\Support\Money;
use App\Support\Nk\NkService;
use App\Support\Pdf\HtmlPdf;
use Illuminate\Support\Str;


/**
 * Stellt für jede Versandart die Empfänger, den Vorschlagstext und die PDF-Anhänge zusammen (Paket 21).
 * Platzhalter im Text: [Anrede], [Absender] und je nach Art weitere (siehe placeholders()).
 */
class Outbox
{
    public const KINDS = ['mahnung', 'nk', 'vertrag', 'uebergabe', 'raten', 'anfrage', 'rundschreiben', 'verbrauch'];

    /**
     * @return array{kind: string, title: string, back: string, subject: string, body: string, placeholders: array, items: array<int, array>, upload: bool}
     *
     * items: [key, label, owner_id, tenant_id, lease_id, ref_id, addresses (string[]), vars (array), pdf (?callable), pdf_name (?string)]
     */
    public static function resolve(string $kind, string $ids, User $user): array
    {
        $idList = collect(explode(',', $ids))->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->values()->all();
        if ($idList === []) {
            throw new MailError('Es wurde nichts zum Versenden ausgewählt.');
        }

        return match ($kind) {
            'mahnung' => self::mahnung($idList, $user),
            'nk' => self::nk($idList[0], $user, $idList[1] ?? null),
            'vertrag' => self::vertrag($idList[0], $user),
            'uebergabe' => self::uebergabe($idList[0], $user),
            'raten' => self::raten($idList[0], $user),
            'anfrage' => self::anfrage($idList[0], $user),
            'rundschreiben' => self::rundschreiben($idList[0], $user),
            'verbrauch' => self::verbrauch($idList[0], (string) ($idList[1] ?? 0), $user, $idList[2] ?? null),
            default => throw new MailError('Unbekannte Versandart.'),
        };
    }

    // ------------------------------------------------------------ Hilfen

    private static function access(?int $ownerId, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }
        abort_if($ownerId === null || ! Owner::visibleTo($user)->whereKey($ownerId)->exists(), 404);
    }

    /** E-Mail-Adressen eines Mieters: eigene Adresse, bei Firmen zusätzlich Ansprechpartner für Rechnungen. */
    public static function tenantAddresses(?Tenant $t): array
    {
        if ($t === null) {
            return [];
        }
        $out = [];
        if ($t->email) {
            $out[] = trim($t->email);
        }
        $contacts = $t->relationLoaded('contacts') ? $t->contacts : $t->contacts()->get();
        $inv = $contacts->where('receives_invoices', true)->pluck('email')->filter()->all();
        $out = array_merge($out, $inv);
        if ($out === []) {
            $out = $contacts->pluck('email')->filter()->take(1)->all();
        }

        return array_values(array_unique(array_filter(array_map('trim', $out), fn ($a) => MailService::validAddress($a))));
    }

    public static function salutation(?Tenant $t): string
    {
        if ($t === null || $t->type === 'firma') {
            return 'Sehr geehrte Damen und Herren,';
        }
        $name = trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: (string) $t->display_name;

        return 'Guten Tag '.$name.',';
    }

    private static function signature(): string
    {
        return MailSettings::get('mail_signature');
    }

    private static function closing(): string
    {
        $sig = self::signature();

        return "Mit freundlichen Grüßen\n[Absender]".($sig !== '' ? "\n\n".$sig : '');
    }

    private static function pdfFromView(string $view, array $data, string $title): string
    {
        return HtmlPdf::render(view($view, $data)->render(), $title);
    }

    public static function fileName(?string ...$parts): string
    {
        $s = Str::slug(implode(' ', array_filter($parts)), '-', 'de');

        return mb_substr($s !== '' ? $s : 'dokument', 0, 90).'.pdf';
    }

    private static function leaseItem(Lease $lease, array $extra): array
    {
        return array_merge([
            'key' => (string) $lease->id,
            'label' => $lease->title(),
            'owner_id' => (int) $lease->owner_id,
            'tenant_id' => $lease->tenant_id,
            'lease_id' => $lease->id,
            'addresses' => self::tenantAddresses($lease->tenant),
            'vars' => [
                'Anrede' => self::salutation($lease->tenant),
                'Absender' => $lease->owner?->name ?? MailSettings::fromName(),
                'Einheit' => trim(($lease->unit?->label ?? '').($lease->unit?->property ? ', '.$lease->unit->property->addressLine() : '')),
                'Mieter' => (string) $lease->tenant?->display_name,
            ],
            'pdf' => null,
            'pdf_name' => null,
        ], $extra);
    }

    // ------------------------------------------------------------ Arten

    private static function mahnung(array $ids, User $user): array
    {
        $notices = DunningNotice::visibleTo($user)->whereIn('id', $ids)->with(['lease.unit.property', 'lease.tenant.contacts', 'lease.owner', 'owner'])->orderBy('id')->get();
        abort_if($notices->isEmpty(), 404);
        $items = [];
        foreach ($notices as $n) {
            if ($n->isCancelled() || ! $n->lease) {
                continue;
            }
            $items[] = self::leaseItem($n->lease, [
                'key' => (string) $n->id,
                'ref_id' => $n->id,
                'label' => $n->levelLabel().' vom '.$n->issued_on->format('d.m.Y').' · '.$n->lease->title(),
                'pdf' => fn () => self::pdfFromView('dunning.print', ['notices' => collect([$n]), 'previous' => collect([$n->id => Mahnwesen::previous($n)])], $n->levelLabel()),
                'pdf_name' => self::fileName($n->levelLabel(), $n->issued_on->format('Y-m-d'), $n->lease->unit?->label),
            ]);
            $last = count($items) - 1;
            $items[$last]['vars'] += [
                'Stufe' => $n->levelLabel(),
                'Datum' => $n->issued_on->format('d.m.Y'),
                'Betrag' => Money::format($n->totalAmount()),
                'Frist' => $n->pay_by->format('d.m.Y'),
            ];
        }
        if ($items === []) {
            throw new MailError('Stornierte Schreiben werden nicht versendet.');
        }

        return [
            'kind' => 'mahnung',
            'title' => count($items) === 1 ? $items[0]['vars']['Stufe'].' per E-Mail senden' : count($items).' Mahnschreiben per E-Mail senden',
            'back' => route('dunning.index'),
            'subject' => '[Stufe] – [Einheit]',
            'body' => "[Anrede]\n\nim Anhang erhalten Sie unsere [Stufe] vom [Datum] zu Ihrem Mietverhältnis [Einheit].\n\nBitte überweisen Sie den offenen Betrag von [Betrag] bis spätestens [Frist].\n\nSollten Sie die Zahlung inzwischen geleistet haben, betrachten Sie diese E-Mail bitte als gegenstandslos. Bei Fragen oder wenn Sie eine Ratenzahlung vereinbaren möchten, melden Sie sich gerne bei uns.\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Stufe', 'Datum', 'Betrag', 'Frist', 'Einheit', 'Mieter', 'Absender'],
            'items' => $items,
            'upload' => false,
        ];
    }

    private static function nk(int $id, User $user, ?int $onlyLease): array
    {
        $s = NkSettlement::visibleTo($user)->with(['property', 'owner'])->findOrFail($id);
        if (! $s->isFinal()) {
            throw new MailError('Die Abrechnung ist noch ein Entwurf. Bitte zuerst abschließen, dann versenden.');
        }
        $result = $s->snapshot() ?? NkService::compute($s);
        $leaseIds = array_map('intval', array_keys($result['leases']));
        if ($onlyLease) {
            abort_unless(in_array($onlyLease, $leaseIds, true), 404);
            $leaseIds = [$onlyLease];
        }
        $leases = Lease::query()->whereIn('id', $leaseIds)->with(['tenant.contacts', 'unit.property', 'owner'])->get()->keyBy('id');
        $items = [];
        foreach ($leaseIds as $lid) {
            $lease = $leases->get($lid);
            if (! $lease) {
                continue;
            }
            $l = $result['leases'][$lid] ?? $result['leases'][(string) $lid];
            $res = (int) $l['result_cents'];
            $items[] = self::leaseItem($lease, [
                'ref_id' => $s->id,
                'pdf' => fn () => self::pdfFromView('nk.print', ['settlement' => $s, 'result' => $result, 'leaseIds' => [$lid], 'leases' => $leases], 'Nebenkostenabrechnung'),
                'pdf_name' => self::fileName('Nebenkostenabrechnung', $s->periodLabel(), $lease->unit?->label),
            ]);
            $last = count($items) - 1;
            $items[$last]['vars'] += [
                'Zeitraum' => $s->periodLabel(),
                'Ergebnis' => $res > 0 ? 'eine Nachzahlung von '.Money::format($res / 100) : ($res < 0 ? 'ein Guthaben von '.Money::format(-$res / 100) : 'weder eine Nachzahlung noch ein Guthaben'),
            ];
        }

        return [
            'kind' => 'nk',
            'title' => 'Nebenkostenabrechnung '.$s->periodLabel().' per E-Mail senden',
            'back' => route('nk.show', $s),
            'subject' => 'Nebenkostenabrechnung [Zeitraum] – [Einheit]',
            'body' => "[Anrede]\n\nim Anhang erhalten Sie die Abrechnung der Betriebskosten für [Zeitraum] zu Ihrem Mietverhältnis [Einheit]. Daraus ergibt sich [Ergebnis].\n\nAlle Einzelheiten finden Sie in der Abrechnung. Bei Fragen melden Sie sich gerne.\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Zeitraum', 'Ergebnis', 'Einheit', 'Mieter', 'Absender'],
            'items' => $items,
            'upload' => false,
        ];
    }

    /** Monatliche Verbrauchsinformation (Paket 26). ids: Objekt, Monat als JJJJMM, optional Mietvertrag. */
    private static function verbrauch(int $propertyId, string $ym, User $user, ?int $onlyLease): array
    {
        $property = Property::query()->with('owner')->findOrFail($propertyId);
        self::access((int) $property->owner_id, $user);
        if (! preg_match('/^(\d{4})(\d{2})$/', $ym, $mm) || (int) $mm[2] < 1 || (int) $mm[2] > 12) {
            throw new MailError('Ungültiger Monat.');
        }
        $month = $mm[1].'-'.$mm[2];
        $info = \App\Support\Nk\Verbrauchsinfo::build($property, $month);
        $items = [];
        foreach ($info['rows'] as $row) {
            $lease = $row['lease'];
            if ($lease === null || ($onlyLease !== null && (int) $lease->id !== $onlyLease)) {
                continue;
            }
            $items[] = self::leaseItem($lease, [
                'ref_id' => $property->id,
                'pdf' => fn () => self::pdfFromView('nk.verbrauch-print', ['property' => $property, 'info' => $info, 'rows' => [$row['unit']->id => $row]], 'Verbrauchsinformation'),
                'pdf_name' => self::fileName('Verbrauchsinformation', $month, $lease->unit?->label),
            ]);
            $items[count($items) - 1]['vars']['Monat'] = $info['label'];
        }
        if ($items === []) {
            throw new MailError('Für diesen Monat gibt es keine vermieteten Einheiten mit Zählern.');
        }

        return [
            'kind' => 'verbrauch',
            'title' => 'Verbrauchsinformation '.$info['label'].' per E-Mail senden',
            'back' => route('consumption.show', ['property' => $property, 'monat' => $month]),
            'subject' => 'Ihr Heizverbrauch im [Monat] – [Einheit]',
            'body' => "[Anrede]\n\nim Anhang erhalten Sie die monatliche Information über Ihren Verbrauch an Heizung und Warmwasser im [Monat] (§ 6a Heizkostenverordnung) für [Einheit].\n\nDie Übersicht vergleicht Ihren Verbrauch mit dem Vormonat, dem gleichen Monat im Vorjahr und einem Vergleichswert. So können Sie Ihren Verbrauch im Blick behalten.\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Monat', 'Einheit', 'Mieter', 'Absender'],
            'items' => $items,
            'upload' => false,
            'month' => $month,
            'property_id' => $property->id,
        ];
    }

    private static function vertrag(int $id, User $user): array
    {
        $c = LeaseContract::query()->with(['lease.unit.property', 'lease.tenant.contacts', 'lease.owner'])->findOrFail($id);
        self::access((int) $c->owner_id, $user);
        if ($c->status === 'storniert') {
            throw new MailError('Ein stornierter Vertrag wird nicht versendet.');
        }
        $sig = ['tenant' => ContractService::signatureDataUri($c->tenant_signature), 'landlord' => ContractService::signatureDataUri($c->landlord_signature)];
        $draft = $c->isDraft();
        $item = self::leaseItem($c->lease, [
            'ref_id' => $c->id,
            'pdf' => fn () => self::pdfFromView('contracts.print', ['c' => $c, 'sig' => $sig], $c->title()),
            'pdf_name' => self::fileName($c->title(), $draft ? 'Entwurf' : null, $c->lease?->unit?->label),
        ]);
        $item['vars']['Vertrag'] = $c->title();

        return [
            'kind' => 'vertrag',
            'title' => $c->title().' per E-Mail senden',
            'back' => route('contracts.show', $c),
            'subject' => ($draft ? 'Entwurf: ' : '').'[Vertrag] – [Einheit]',
            'body' => $draft
                ? "[Anrede]\n\nim Anhang erhalten Sie den Entwurf des Mietvertrags für [Einheit] zur Durchsicht. Bitte prüfen Sie die Angaben und melden Sie sich, falls etwas geändert werden soll. Unterschrieben wird beim gemeinsamen Termin.\n\n".self::closing()
                : "[Anrede]\n\nim Anhang erhalten Sie Ihre Ausfertigung des unterschriebenen Mietvertrags für [Einheit]. Bitte bewahren Sie ihn gut auf.\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Vertrag', 'Einheit', 'Mieter', 'Absender'],
            'items' => [$item],
            'upload' => false,
        ];
    }

    private static function uebergabe(int $id, User $user): array
    {
        $p = HandoverProtocol::query()->with(['lease.unit.property', 'lease.tenant.contacts', 'lease.owner'])->findOrFail($id);
        self::access((int) $p->owner_id, $user);
        $item = self::leaseItem($p->lease, [
            'ref_id' => $p->id,
            'pdf' => fn () => self::pdfFromView('handover.print', HandoverController::viewData($p, true), $p->title()),
            'pdf_name' => self::fileName($p->title(), $p->handover_on->format('Y-m-d'), $p->lease?->unit?->label),
        ]);
        $item['vars']['Protokoll'] = $p->title();
        $item['vars']['Datum'] = $p->handover_on->format('d.m.Y');

        return [
            'kind' => 'uebergabe',
            'title' => $p->title().' per E-Mail senden',
            'back' => route('handover.show', $p),
            'subject' => '[Protokoll] vom [Datum] – [Einheit]',
            'body' => "[Anrede]\n\nim Anhang erhalten Sie das [Protokoll] vom [Datum] für [Einheit].\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Protokoll', 'Datum', 'Einheit', 'Mieter', 'Absender'],
            'items' => [$item],
            'upload' => false,
        ];
    }

    private static function raten(int $id, User $user): array
    {
        $plan = InstallmentPlan::visibleTo($user)->with(['installments', 'lease.unit.property', 'lease.tenant.contacts', 'lease.owner'])->findOrFail($id);
        $item = self::leaseItem($plan->lease, [
            'ref_id' => $plan->id,
            'pdf' => fn () => self::pdfFromView('interest.plan-print', ['plan' => $plan], 'Ratenzahlungsvereinbarung'),
            'pdf_name' => self::fileName('Ratenzahlungsvereinbarung', $plan->agreed_on->format('Y-m-d'), $plan->lease?->unit?->label),
        ]);
        $item['vars']['Datum'] = $plan->agreed_on->format('d.m.Y');
        $item['vars']['Betrag'] = Money::format($plan->debt);
        $item['vars']['Raten'] = (string) $plan->installments->count();

        return [
            'kind' => 'raten',
            'title' => 'Ratenvereinbarung per E-Mail senden',
            'back' => route('installments.show', $plan),
            'subject' => 'Ratenzahlungsvereinbarung – [Einheit]',
            'body' => "[Anrede]\n\nwie besprochen erhalten Sie im Anhang die Ratenzahlungsvereinbarung vom [Datum] über [Betrag] in [Raten] Raten. Bitte senden Sie uns ein unterschriebenes Exemplar zurück und denken Sie daran, die laufende Miete weiterhin pünktlich zu zahlen.\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Datum', 'Betrag', 'Raten', 'Einheit', 'Mieter', 'Absender'],
            'items' => [$item],
            'upload' => false,
        ];
    }

    private static function anfrage(int $id, User $user): array
    {
        $q = Inquiry::visibleTo($user)->with('listing')->findOrFail($id);
        $quoted = collect(preg_split('/\r?\n/', (string) $q->message))->map(fn ($l) => '> '.$l)->implode("\n");
        $sender = $q->owner_id ? (Owner::find($q->owner_id)?->name ?? MailSettings::fromName()) : MailSettings::fromName();
        $item = [
            'key' => (string) $q->id,
            'label' => $q->name.($q->company ? ' ('.$q->company.')' : '').($q->listing ? ' · '.$q->listing->title : ''),
            'owner_id' => $q->owner_id,
            'tenant_id' => null,
            'lease_id' => null,
            'ref_id' => $q->id,
            'addresses' => MailService::validAddress((string) $q->email) ? [(string) $q->email] : [],
            'vars' => ['Anrede' => 'Guten Tag '.$q->name.',', 'Absender' => $sender, 'Inserat' => $q->listing?->title ?? ''],
            'pdf' => null,
            'pdf_name' => null,
        ];

        return [
            'kind' => 'anfrage',
            'title' => 'Anfrage beantworten',
            'back' => route('inquiries.show', $q),
            'subject' => $q->listing ? 'Ihre Anfrage: [Inserat]' : 'Ihre Anfrage',
            'body' => "[Anrede]\n\nvielen Dank für Ihre Anfrage.\n\n\n\n".self::closing()."\n\n----- Ihre Nachricht vom ".$q->created_at->timezone('Europe/Berlin')->format('d.m.Y')." -----\n".$quoted,
            'placeholders' => ['Anrede', 'Inserat', 'Absender'],
            'items' => [$item],
            'upload' => true,
        ];
    }

    private static function rundschreiben(int $id, User $user): array
    {
        $p = Property::query()->findOrFail($id);
        self::access((int) $p->owner_id, $user);
        $today = date('Y-m-d');
        $leases = Lease::query()->whereIn('unit_id', $p->units()->select('id'))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->with(['tenant.contacts', 'unit.property', 'owner'])->orderBy('unit_id')->get();
        $items = [];
        foreach ($leases as $l) {
            $items[] = self::leaseItem($l, ['ref_id' => $p->id]);
        }
        if ($items === []) {
            throw new MailError('In diesem Objekt gibt es keine laufenden Mietverträge.');
        }

        return [
            'kind' => 'rundschreiben',
            'title' => 'Rundschreiben an die Mieter von '.$p->name,
            'back' => route('properties.show', $p),
            'subject' => '',
            'body' => "[Anrede]\n\n\n\n".self::closing(),
            'placeholders' => ['Anrede', 'Einheit', 'Mieter', 'Absender'],
            'items' => $items,
            'upload' => true,
        ];
    }

    /** Platzhalter ersetzen. */
    public static function fill(string $text, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['['.$k.']'] = (string) $v;
        }

        return strtr($text, $map);
    }
}
