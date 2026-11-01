<?php

namespace App\Support\Mail;

use App\Models\Applicant;
use App\Models\Owner;
use App\Models\ReceivedEmail;
use App\Models\Tenant;
use App\Models\TenantContact;
use App\Models\Vendor;
use App\Support\Mail\Imap\ImapClient;
use App\Support\Mail\Imap\MimeParser;
use App\Support\Settings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Posteingang: holt neue E-Mails aus dem Postfach (IMAP, z. B. imap.ionos.de) und legt sie im CRM ab.
 * Im Postfach bleibt alles unverändert (nur lesender Zugriff, Nachrichten bleiben ungelesen).
 */
class Inbox
{
    /** Beim allerersten Abruf werden nur E-Mails der letzten Tage übernommen, nicht das ganze Postfach. */
    public const FIRST_DAYS = 30;

    /** Höchstens so viele E-Mails pro Abruf (der Rest folgt beim nächsten Mal). */
    public const PER_RUN = 40;

    /** Größere E-Mails werden ohne Inhalt übernommen (Hinweis auf das Webmail). */
    public const MAX_BYTES = 25 * 1024 * 1024;

    public const FOLDER = 'INBOX';

    /** Im Test: rohe Nachrichten [uid => raw] statt echter Verbindung */
    public static ?array $fake = null;

    public static function lastFetch(): ?string
    {
        return Settings::get('imap_last_fetch');
    }

    public static function lastError(): ?string
    {
        return Settings::get('imap_last_error');
    }

    /** Neue E-Mails abholen. @return array{neu: int, fehler: ?string, rest: int} */
    public static function fetch(?int $max = null): array
    {
        $max ??= self::PER_RUN;
        if (! Schema::hasTable('received_emails')) {
            return ['neu' => 0, 'fehler' => 'Die Tabelle für den Posteingang fehlt noch.', 'rest' => 0];
        }
        if (self::$fake === null && ! MailSettings::receiveConfigured()) {
            return ['neu' => 0, 'fehler' => 'Der E-Mail-Empfang ist nicht eingerichtet (Verwaltung → E-Mail einrichten).', 'rest' => 0];
        }

        $lockFile = storage_path('logs/mail-abruf.lock');
        @mkdir(dirname($lockFile), 0775, true);
        $lock = @fopen($lockFile, 'c');
        if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
            return ['neu' => 0, 'fehler' => null, 'rest' => 0]; // läuft schon
        }
        @set_time_limit(180);

        $new = 0;
        $rest = 0;
        $error = null;
        $client = null;
        try {
            if (self::$fake !== null) {
                $validity = 1;
                $all = array_keys(self::$fake);
                sort($all);
            } else {
                $client = new ImapClient(MailSettings::get('imap_host'), (int) MailSettings::get('imap_port') ?: 993);
                $client->connect();
                $client->login(MailSettings::get('mail_username'), (string) MailSettings::password());
                $box = $client->select(self::FOLDER);
                $validity = $box['uidvalidity'];
                $all = null;
            }

            $storedValidity = (int) Settings::get('imap_state_uidvalidity', '0');
            $lastUid = $storedValidity === $validity ? (int) Settings::get('imap_state_last_uid', '0') : 0;
            $first = $storedValidity === 0;

            if ($all !== null) {
                $uids = array_values(array_filter($all, fn ($u) => $u > $lastUid));
            } elseif ($lastUid > 0) {
                $uids = array_values(array_filter($client->uidSearch('UID '.($lastUid + 1).':*'), fn ($u) => $u > $lastUid));
            } else {
                $since = now()->subDays(self::FIRST_DAYS)->locale('en')->format('j-M-Y');
                $uids = $client->uidSearch('SINCE '.$since);
            }

            $rest = max(0, count($uids) - $max);
            foreach (array_slice($uids, 0, $max) as $uid) {
                $exists = ReceivedEmail::query()->where('folder', self::FOLDER)->where('uidvalidity', $validity)->where('uid', $uid)->exists();
                if (! $exists) {
                    if (self::$fake !== null) {
                        $raw = self::$fake[$uid];
                        $size = strlen($raw);
                        $tooBig = false;
                    } else {
                        $size = $client->size($uid);
                        $tooBig = $size > self::MAX_BYTES;
                        $raw = $tooBig ? $client->fetchHeader($uid) : $client->fetchRaw($uid);
                    }
                    if ($raw !== null && self::store($raw, $uid, $validity, $size, $tooBig)) {
                        $new++;
                    }
                }
                $lastUid = max($lastUid, $uid);
                Settings::putMany(['imap_state_uidvalidity' => (string) $validity, 'imap_state_last_uid' => (string) $lastUid]);
            }
            if ($first || $uids === []) {
                Settings::putMany(['imap_state_uidvalidity' => (string) $validity, 'imap_state_last_uid' => (string) $lastUid]);
            }
            $client?->logout();
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
            try {
                $client?->logout();
            } catch (Throwable) {
            }
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        Settings::putMany([
            'imap_last_fetch' => now()->toDateTimeString(),
            'imap_last_error' => $error,
        ]);

        return ['neu' => $new, 'fehler' => $error, 'rest' => $rest];
    }

    /** Eine rohe E-Mail auswerten und speichern. false = übersprungen (z. B. eigene Kopie aus dem CRM). */
    public static function store(string $raw, int $uid, int $validity, int $size, bool $tooBig = false): bool
    {
        $m = MimeParser::parse($raw);

        // Blindkopien, die das CRM selbst verschickt hat, nicht noch einmal als Eingang zeigen
        if (isset($m['headers']['x-crm-gesendet'])) {
            return false;
        }
        if ($m['message_id'] !== '' && ReceivedEmail::query()->where('message_id', mb_substr($m['message_id'], 0, 255))->exists()) {
            return false;
        }

        $body = $tooBig
            ? 'Diese E-Mail ist sehr groß ('.number_format($size / 1048576, 1, ',', '.').' MB) und wurde nur mit Betreff übernommen. Bitte im Webmail von IONOS öffnen.'
            : $m['text'];
        // Sehr lange Texte begrenzen (mediumText reicht weit, aber die Ansicht soll schnell bleiben)
        if (strlen($body) > 500000) {
            $body = mb_strcut($body, 0, 500000).'…';
        }

        $links = self::match($m['from_address']);
        $mail = ReceivedEmail::create(array_merge($links, [
            'folder' => self::FOLDER,
            'uidvalidity' => $validity,
            'uid' => $uid,
            'message_id' => $m['message_id'] !== '' ? mb_substr($m['message_id'], 0, 255) : null,
            'in_reply_to' => $m['in_reply_to'] !== '' ? mb_substr($m['in_reply_to'], 0, 255) : null,
            'references' => $m['references'] !== '' ? mb_substr($m['references'], 0, 4000) : null,
            'from_address' => mb_substr($m['from_address'] ?: 'unbekannt', 0, 190),
            'from_name' => $m['from_name'] !== '' ? mb_substr($m['from_name'], 0, 190) : null,
            'reply_to' => $m['reply_to'] !== '' && $m['reply_to'] !== $m['from_address'] ? mb_substr($m['reply_to'], 0, 190) : null,
            'to_addresses' => mb_substr($m['to'], 0, 1000),
            'cc' => $m['cc'] !== '' ? mb_substr($m['cc'], 0, 1000) : null,
            'subject' => mb_substr($m['subject'] !== '' ? $m['subject'] : '(ohne Betreff)', 0, 300),
            'body' => $body,
            'size' => min($size, 4294967295),
            'received_at' => $m['date'] ? \Illuminate\Support\Carbon::instance($m['date'])->setTimezone(config('app.timezone')) : now(),
            'attachments' => [],
        ]));

        if (! $tooBig && $m['attachments'] !== []) {
            $files = [];
            foreach ($m['attachments'] as $i => $a) {
                $path = 'posteingang/'.$mail->id.'/'.($i + 1).'-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $a['name']);
                Storage::disk('local')->put($path, $a['data']);
                $files[] = ['name' => $a['name'], 'mime' => $a['mime'], 'size' => strlen($a['data']), 'path' => $path];
            }
            $mail->attachments = $files;
            $mail->save();
        }

        return true;
    }

    /**
     * Absender einem Mieter, Eigentümer, Lieferanten oder Interessenten zuordnen.
     *
     * @return array{owner_id: ?int, tenant_id: ?int, vendor_id: ?int, applicant_id: ?int}
     */
    public static function match(string $address): array
    {
        $out = ['owner_id' => null, 'tenant_id' => null, 'vendor_id' => null, 'applicant_id' => null];
        $a = mb_strtolower(trim($address));
        if ($a === '' || ! str_contains($a, '@')) {
            return $out;
        }
        $eq = fn ($q, string $col = 'email') => $q->whereRaw('LOWER(TRIM('.$col.')) = ?', [$a]);

        $tenant = $eq(Tenant::query())->orderByDesc('id')->first();
        if (! $tenant) {
            $contact = $eq(TenantContact::query())->orderByDesc('id')->first();
            $tenant = $contact ? Tenant::query()->find($contact->tenant_id) : null;
        }
        if ($tenant) {
            $out['tenant_id'] = $tenant->id;
            $out['owner_id'] = $tenant->owner_id;
        }
        if ($out['owner_id'] === null && ($owner = $eq(Owner::query())->first())) {
            $out['owner_id'] = $owner->id;
        }
        if ($vendor = $eq(Vendor::query())->first()) {
            $out['vendor_id'] = $vendor->id;
        }
        if (! $tenant && ($ap = $eq(Applicant::query())->orderByDesc('id')->first())) {
            $out['applicant_id'] = $ap->id;
            $out['owner_id'] ??= $ap->owner_id;
        }

        return $out;
    }

    /** Verbindung prüfen, ohne etwas abzuholen. @return array{ok: bool, text: string} */
    public static function test(): array
    {
        if (! MailSettings::receiveConfigured()) {
            return ['ok' => false, 'text' => 'Bitte zuerst Benutzername, Passwort und Posteingangsserver speichern.'];
        }
        try {
            $client = new ImapClient(MailSettings::get('imap_host'), (int) MailSettings::get('imap_port') ?: 993);
            $client->connect();
            $client->login(MailSettings::get('mail_username'), (string) MailSettings::password());
            $box = $client->select(self::FOLDER);
            $client->logout();

            return ['ok' => true, 'text' => 'Verbindung klappt. Im Posteingang des Postfachs liegen '.$box['exists'].' E-Mail(s).'];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'text' => $e->getMessage()];
        }
    }
}
