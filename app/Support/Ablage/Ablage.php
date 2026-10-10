<?php

namespace App\Support\Ablage;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/** Dokumentenablage (Paket 22): Zuordnung, Kategorien, Speichern, Fristen. */
class Ablage
{
    /** Art => [Bezeichnung, Modell] */
    public const TYPES = [
        'owner' => ['Eigentümer', Owner::class],
        'property' => ['Objekt', Property::class],
        'unit' => ['Einheit', Unit::class],
        'tenant' => ['Mieter', Tenant::class],
        'lease' => ['Mietvertrag', Lease::class],
    ];

    /** Schlüssel => [Bezeichnung, erlaubt bei, Ablaufdatum sinnvoll, Hinweis] */
    public const CATEGORIES = [
        'versicherung' => ['Versicherung', ['owner', 'property'], true, 'Ablaufdatum = Vertragsende oder Kündigungsfrist'],
        'grundbuch' => ['Grundbuchauszug', ['property'], false, ''],
        'kaufvertrag' => ['Kaufvertrag', ['owner', 'property'], false, ''],
        'energieausweis' => ['Energieausweis', ['property', 'unit'], true, 'Gültig 10 Jahre ab Ausstellung'],
        'grundsteuer' => ['Grundsteuerbescheid', ['owner', 'property'], false, ''],
        'wartung' => ['Wartung und Prüfung', ['property', 'unit'], true, 'z. B. Heizung, Rauchmelder, Trinkwasser – Ablaufdatum = nächster Termin'],
        'plan' => ['Pläne und Grundrisse', ['property', 'unit'], false, ''],
        'foto' => ['Fotos', ['property', 'unit', 'lease'], false, ''],
        'darlehen' => ['Darlehen und Finanzierung', ['owner', 'property'], true, 'Ablaufdatum = Ende der Zinsbindung'],
        'vollmacht' => ['Vollmacht', ['owner'], true, ''],
        'steuer' => ['Steuerbescheid', ['owner'], false, ''],
        'mietvertrag' => ['Mietvertrag', ['tenant', 'lease'], false, ''],
        'uebergabe' => ['Übergabeprotokoll', ['lease'], false, ''],
        'mahnung' => ['Mahnung', ['tenant', 'lease'], false, ''],
        'abrechnung' => ['Nebenkostenabrechnung', ['tenant', 'lease'], false, ''],
        'raten' => ['Ratenvereinbarung', ['lease'], false, ''],
        'ausweis' => ['Ausweiskopie', ['tenant'], true, 'Nur geschwärzte Kopie und nur so lange wie nötig aufbewahren (Datenschutz).'],
        'bonitaet' => ['Bonität und Einkommen', ['tenant'], false, 'Nach Abschluss des Mietvertrags löschen, wenn nicht mehr nötig (Datenschutz).'],
        'schriftverkehr' => ['Schriftverkehr', ['owner', 'property', 'unit', 'tenant', 'lease'], false, ''],
        'sonstiges' => ['Sonstiges', ['owner', 'property', 'unit', 'tenant', 'lease'], true, ''],
    ];

    public static function categoriesFor(string $type): array
    {
        return array_filter(self::CATEGORIES, fn ($c) => in_array($type, $c[1], true));
    }

    /** Ziel laden: [Modell, owner_id, Bezeichnung, Link]. null, wenn es das Ziel nicht gibt. */
    public static function target(string $type, int $id): ?array
    {
        if (! isset(self::TYPES[$type])) {
            return null;
        }
        /** @var Model|null $m */
        $m = (self::TYPES[$type][1])::query()->find($id);
        if ($m === null) {
            return null;
        }

        return match ($type) {
            'owner' => [$m, (int) $m->id, $m->name, route('owners.show', $m).'#dokumente'],
            'property' => [$m, (int) $m->owner_id, $m->name, route('properties.show', $m).'#dokumente'],
            'unit' => [$m, (int) $m->property?->owner_id, $m->label.($m->property ? ', '.$m->property->name : ''), route('units.edit', $m).'#dokumente'],
            'tenant' => [$m, (int) $m->owner_id, (string) $m->display_name, route('tenants.show', $m).'#dokumente'],
            'lease' => [$m, (int) $m->owner_id, $m->title(), route('leases.show', $m).'#dokumente'],
        };
    }

    /**
     * Dokument speichern.
     *
     * @param  array{content: string, mime: string, size: int, sha256: string, name: string}  $file
     * @param  array{category: string, title?: ?string, doc_date?: ?string, expires_on?: ?string, remind_days?: ?int, note?: ?string}  $meta
     */
    public static function store(string $type, int $id, array $file, array $meta, ?User $user, string $source = 'upload'): Document
    {
        $t = self::target($type, $id);
        abort_if($t === null, 404);
        $path = DocumentStore::put($file['content']);
        try {
            $title = trim((string) ($meta['title'] ?? ''));
            if ($title === '') {
                $title = pathinfo($file['name'], PATHINFO_FILENAME) ?: (self::CATEGORIES[$meta['category']][0] ?? 'Dokument');
            }
            $doc = Document::create([
                'owner_id' => $t[1],
                'attach_type' => $type,
                'attach_id' => $id,
                'category' => $meta['category'],
                'title' => mb_substr($title, 0, 190),
                'path' => $path,
                'original_name' => mb_substr($file['name'], 0, 190),
                'mime' => $file['mime'],
                'size' => $file['size'],
                'sha256' => $file['sha256'],
                'doc_date' => $meta['doc_date'] ?? null,
                'expires_on' => $meta['expires_on'] ?? null,
                'remind_days' => (int) ($meta['remind_days'] ?? 30) ?: 30,
                'note' => $meta['note'] ?? null,
                'source' => $source,
                'created_by' => $user?->id,
            ]);
        } catch (Throwable $e) {
            DocumentStore::forget($path);
            throw $e;
        }

        // Neueres Dokument mit Ablaufdatum ersetzt ältere derselben Art (z. B. neue Versicherungspolice)
        if ($doc->expires_on !== null) {
            Document::query()->for($type, $id)->where('category', $doc->category)->where('id', '!=', $doc->id)
                ->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<', $doc->expires_on->format('Y-m-d'))
                ->update(['archived' => true]);
        }

        return $doc;
    }

    /** Erzeugtes Schreiben (z. B. per E-Mail versendet) ablegen. Gleicher Inhalt wird nicht doppelt abgelegt. */
    public static function storeGenerated(string $type, int $id, string $category, string $title, string $pdf, string $fileName, ?User $user, string $source = 'email'): ?Document
    {
        try {
            $sha = hash('sha256', $pdf);
            if (Document::query()->for($type, $id)->where('sha256', $sha)->exists()) {
                return null;
            }

            return self::store($type, $id, ['content' => $pdf, 'mime' => 'application/pdf', 'size' => strlen($pdf), 'sha256' => $sha, 'name' => $fileName], [
                'category' => $category, 'title' => $title, 'doc_date' => date('Y-m-d'),
            ], $user, $source);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Abgeschlossenes Schreiben (Vertrag, Protokoll) als PDF beim Mietvertrag ablegen. */
    public static function fileFinished(string $kind, int $id, User $user): void
    {
        try {
            $box = \App\Support\Mail\Outbox::resolve($kind, (string) $id, $user);
            $item = $box['items'][0] ?? null;
            if ($item && $item['pdf'] && $item['lease_id']) {
                $cat = $kind === 'vertrag' ? 'mietvertrag' : 'uebergabe';
                $title = ($kind === 'vertrag' ? ($item['vars']['Vertrag'] ?? 'Mietvertrag') : ($item['vars']['Protokoll'] ?? 'Übergabeprotokoll').' vom '.($item['vars']['Datum'] ?? '')).' (unterschrieben)';
                self::storeGenerated('lease', (int) $item['lease_id'], $cat, $title, ($item['pdf'])(), $item['pdf_name'], $user, 'system');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Dokumente für eine Seite: eigene und – bei Mieter/Mietvertrag – die zugehörigen. */
    public static function listFor(string $type, int $id, User $user, bool $archived = false): Collection
    {
        $q = Document::visibleTo($user)->where(function ($w) use ($type, $id) {
            $w->where(fn ($x) => $x->where('attach_type', $type)->where('attach_id', $id));
            if ($type === 'tenant') {
                $w->orWhere(fn ($x) => $x->where('attach_type', 'lease')->whereIn('attach_id', Lease::query()->where('tenant_id', $id)->select('id')));
            }
            if ($type === 'lease') {
                $tenant = Lease::query()->whereKey($id)->value('tenant_id');
                if ($tenant) {
                    $w->orWhere(fn ($x) => $x->where('attach_type', 'tenant')->where('attach_id', $tenant));
                }
            }
            if ($type === 'property') {
                $w->orWhere(fn ($x) => $x->where('attach_type', 'unit')->whereIn('attach_id', Unit::query()->where('property_id', $id)->select('id')));
            }
        });
        if (! $archived) {
            $q->where('archived', false);
        }

        return $q->orderByRaw('archived asc')->orderByDesc('doc_date')->orderByDesc('id')->get();
    }

    public static function delete(Document $doc): void
    {
        $path = $doc->path;
        $title = $doc->title;
        $doc->delete();
        if (! Document::query()->where('path', $path)->exists()) {
            DocumentStore::forget($path);
        }
        Audit::log('document.deleted', null, 'Dokument gelöscht: '.$title);
    }

    /** Dokumente, deren Ablaufdatum erreicht ist oder bald erreicht wird (nicht archiviert). */
    public static function expiring(User $user, ?int $ownerId = null, ?string $today = null, int $days = 60): Collection
    {
        $today ??= date('Y-m-d');

        return Document::visibleTo($user)->where('archived', false)->whereNotNull('expires_on')
            ->when($ownerId, fn ($q) => $q->where('documents.owner_id', $ownerId))
            ->where('expires_on', '<=', date('Y-m-d', strtotime($today.' +'.$days.' days')))
            ->orderBy('expires_on')->get();
    }

    /** Täglich: Erinnerungs-E-Mail für Dokumente, die bald ablaufen. @return int Anzahl erinnerter Dokumente */
    public static function sendReminders(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $due = Document::query()->where('archived', false)->whereNotNull('expires_on')->whereNull('reminded_at')
            ->whereRaw('expires_on <= DATE_ADD(?, INTERVAL remind_days DAY)', [$today])
            ->orderBy('expires_on')->limit(100)->get();
        if ($due->isEmpty()) {
            return 0;
        }
        $to = \App\Support\Mail\MailSettings::copyAddress();
        if (! \App\Support\Mail\MailService::validAddress($to) || (\App\Support\Mail\MailService::$fake === null && ! \App\Support\Mail\MailSettings::configured())) {
            return 0;
        }
        $lines = [];
        foreach ($due as $d) {
            $t = self::target($d->attach_type, (int) $d->attach_id);
            $state = $d->expires_on->format('Y-m-d') < $today ? 'abgelaufen am' : 'läuft ab am';
            $lines[] = '- '.$d->title.' ('.$d->categoryLabel().', '.($t[2] ?? '').'): '.$state.' '.$d->expires_on->format('d.m.Y')."\n  ".($t[3] ?? route('documents.index'));
        }
        $body = "Guten Tag,\n\nfolgende Dokumente laufen bald ab oder sind abgelaufen:\n\n".implode("\n", $lines)
            ."\n\nBitte neue Unterlagen anfordern und im CRM ablegen. Ein neues Dokument derselben Art mit späterem Ablaufdatum ersetzt das alte automatisch.\n\nAlle Fristen: ".route('documents.index', ['frist' => 'bald']);
        $log = \App\Support\Mail\MailService::send([$to], count($due) === 1 ? 'Dokument läuft ab: '.$due->first()->title : count($due).' Dokumente laufen ab', $body, [], ['kind' => 'hinweis'], null, false);
        if ($log->status !== 'gesendet') {
            return 0;
        }
        Document::query()->whereIn('id', $due->pluck('id'))->update(['reminded_at' => now()]);

        return $due->count();
    }
}
