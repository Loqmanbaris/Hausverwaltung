<?php

namespace App\Support\Mail;

use App\Models\SentEmail;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/** Versand über den SMTP-Server des Postfachs (Paket 21). Jede E-Mail wird im Verlauf gespeichert. */
class MailService
{
    /** @var array<int, array>|null Im Test: gesendete E-Mails statt echtem Versand */
    public static ?array $fake = null;

    public static function fake(): void
    {
        self::$fake = [];
    }

    public static function validAddress(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! preg_match('/[\r\n,;]/', $email);
    }

    /**
     * @param  string[]  $to
     * @param  array<int, array{name: string, data: string, mime: string}>  $attachments
     * @param  array{kind: string, ref_id?: ?int, owner_id?: ?int, tenant_id?: ?int, lease_id?: ?int}  $ctx
     */
    /** @param  array{in_reply_to?: string, references?: string}  $thread  Für Antworten: Bezug auf die ursprüngliche E-Mail */
    public static function send(array $to, string $subject, string $body, array $attachments, array $ctx, ?User $user, bool $copy = true, array $thread = []): SentEmail
    {
        $to = array_values(array_unique(array_filter(array_map('trim', $to), fn ($a) => $a !== '')));
        $log = new SentEmail([
            'owner_id' => $ctx['owner_id'] ?? null,
            'tenant_id' => $ctx['tenant_id'] ?? null,
            'lease_id' => $ctx['lease_id'] ?? null,
            'kind' => $ctx['kind'],
            'ref_id' => $ctx['ref_id'] ?? null,
            'recipients' => mb_substr(implode(', ', $to), 0, 500),
            'subject' => mb_substr($subject, 0, 200),
            'body' => $body,
            'attachments' => array_map(fn ($a) => $a['name'], $attachments),
            'user_id' => $user?->id,
        ]);

        try {
            if ($to === []) {
                throw new RuntimeException('Keine Empfänger-Adresse.');
            }
            foreach ($to as $a) {
                if (! self::validAddress($a)) {
                    throw new RuntimeException('Ungültige E-Mail-Adresse: '.$a);
                }
            }
            if (self::$fake === null && ! MailSettings::configured()) {
                throw new RuntimeException('Der E-Mail-Versand ist noch nicht eingerichtet (Verwaltung → E-Mail).');
            }
            $from = MailSettings::get('mail_from_address') ?: 'crm@example.org';
            $email = (new Email)
                ->from(new Address($from, MailSettings::fromName()))
                ->replyTo($from)
                ->subject($subject)
                ->text($body);
            foreach ($to as $a) {
                $email->addTo($a);
            }
            // Kennzeichen: Die Blindkopie dieser E-Mail erscheint nicht im Posteingang des CRM
            $email->getHeaders()->addTextHeader('X-CRM-Gesendet', '1');
            foreach (['in_reply_to' => 'In-Reply-To', 'references' => 'References'] as $k => $h) {
                $v = trim((string) ($thread[$k] ?? ''));
                if ($v !== '' && preg_match('/^[\x21-\x7E ]{3,4000}$/', $v)) {
                    try {
                        $email->getHeaders()->addIdHeader($h, array_values(array_filter(array_map(fn ($x) => trim($x, '<> '), preg_split('/\s+/', $v) ?: []))));
                    } catch (Throwable) {
                        // ungültige Kennung: Antwort trotzdem senden, nur ohne Bezug
                    }
                }
            }
            $bcc = MailSettings::copyAddress();
            if ($copy && $bcc !== '' && self::validAddress($bcc) && ! in_array(mb_strtolower($bcc), array_map('mb_strtolower', $to), true)) {
                $email->addBcc($bcc);
            }
            foreach ($attachments as $a) {
                $email->attach($a['data'], $a['name'], $a['mime']);
            }

            if (self::$fake !== null) {
                self::$fake[] = ['to' => $to, 'bcc' => $copy ? $bcc : '', 'subject' => $subject, 'body' => $body, 'attachments' => $attachments, 'ctx' => $ctx, 'thread' => $thread, 'raw' => $email->toString()];
            } else {
                $port = (int) MailSettings::get('mail_port') ?: 587;
                $transport = new EsmtpTransport(MailSettings::get('mail_host'), $port, $port === 465);
                $transport->setUsername(MailSettings::get('mail_username'));
                $transport->setPassword((string) MailSettings::password());
                $stream = $transport->getStream();
                if (method_exists($stream, 'setTimeout')) {
                    $stream->setTimeout(20);
                }
                (new Mailer($transport))->send($email);
            }
            $log->status = 'gesendet';
        } catch (Throwable $e) {
            $log->status = 'fehler';
            $log->error = mb_substr(self::friendlyError($e->getMessage()), 0, 1000);
        }
        $log->save();

        return $log;
    }

    /** Typische Fehlermeldungen verständlich machen. */
    public static function friendlyError(string $msg): string
    {
        $m = mb_strtolower($msg);

        return match (true) {
            str_contains($m, 'authentication') || str_contains($m, '535') => 'Anmeldung am E-Mail-Server fehlgeschlagen. Bitte Benutzername (die vollständige E-Mail-Adresse) und Passwort prüfen.',
            str_contains($m, 'connection could not be established') || str_contains($m, 'timed out') || str_contains($m, 'getaddrinfo') => 'Keine Verbindung zum E-Mail-Server. Bitte Server und Port prüfen (IONOS: smtp.ionos.de, Port 587). ('.$msg.')',
            str_contains($m, '550') || str_contains($m, '553') => 'Der Server hat die Adresse abgelehnt. Bitte Absender- und Empfänger-Adresse prüfen. ('.$msg.')',
            default => $msg,
        };
    }
}
