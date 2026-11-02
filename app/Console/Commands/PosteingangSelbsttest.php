<?php

namespace App\Console\Commands;

use App\Support\Mail\Imap\ImapClient;
use App\Support\Mail\Imap\MimeParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest E-Mail-Empfang (Posteingang). Baut keine Verbindung auf, prüft nur Aufbau und Auswertung. */
class PosteingangSelbsttest extends Command
{
    protected $signature = 'posteingang:selbsttest';

    protected $description = 'Selbsttest E-Mail-Empfang (Posteingang)';

    public function handle(): int
    {
        $raw = "From: =?UTF-8?Q?J=C3=BCrgen_M=C3=BCller?= <Juergen@Example.DE>\r\n"
            ."To: info@example.org\r\nSubject: =?ISO-8859-1?Q?Heizung_f=E4llt_aus?=\r\n"
            ."Date: Sun, 1 Nov 2026 10:00:00 +0100\r\nMessage-ID: <selbsttest@example.org>\r\n"
            ."MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"G\"\r\n\r\n"
            ."--G\r\nContent-Type: text/plain; charset=iso-8859-1\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            ."Gr=FC=DFe aus der Wohnung\r\n"
            ."--G\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename*=UTF-8''Rechnung%20M%C3%A4rz.pdf\r\n"
            ."Content-Transfer-Encoding: base64\r\n\r\n".base64_encode('%PDF-1.4 Test')."\r\n--G--\r\n";
        $m = [];
        try {
            $m = MimeParser::parse($raw);
        } catch (\Throwable $e) {
            $this->line('  '.$e->getMessage());
        }
        $html = MimeParser::htmlToText('<style>x{}</style><p>Hallo&nbsp;Welt</p><script>alert(1)</script>');

        $checks = [
            'Tabelle' => Schema::hasTable('received_emails') && Schema::hasColumn('received_emails', 'uidvalidity'),
            'Seiten' => Route::has('inbox.index') && Route::has('inbox.show') && Route::has('inbox.fetch') && Route::has('inbox.reply')
                && Route::has('inbox.attachment') && Route::has('inbox.destroy') && Route::has('mail.write') && Route::has('mail.write.send') && Route::has('mail.test.receive'),
            'Ansichten' => view()->exists('mail.outlook'),
            'HTML-Ansicht abgeschottet' => Route::has('inbox.html') && ! str_contains(\App\Support\Mail\MailView::safeHtml(new \App\Models\ReceivedEmail(['body_html' => '<p onclick="x()">Hallo</p><script>alert(1)</script><a href="javascript:x()">y</a>'])), 'script')
                && ! str_contains(\App\Support\Mail\MailView::safeHtml(new \App\Models\ReceivedEmail(['body_html' => '<p onclick="x()">Hallo</p>'])), 'onclick'),
            'IMAP-Zugang' => class_exists(ImapClient::class) && function_exists('stream_socket_client') && extension_loaded('openssl'),
            'Absender und Betreff lesen' => ($m['from_address'] ?? '') === 'juergen@example.de' && ($m['from_name'] ?? '') === 'Jürgen Müller' && ($m['subject'] ?? '') === 'Heizung fällt aus',
            'Text lesen (Umlaute)' => ($m['text'] ?? '') === 'Grüße aus der Wohnung',
            'Anhang lesen' => ($m['attachments'][0]['name'] ?? '') === 'Rechnung März.pdf' && ($m['attachments'][0]['data'] ?? '') === '%PDF-1.4 Test',
            'HTML nur als Text' => trim($html) === 'Hallo Welt',
            'Abruf-Befehl' => array_key_exists('mail:abrufen', \Illuminate\Support\Facades\Artisan::all()),
        ];
        $this->line('Selbsttest Posteingang (E-Mail-Empfang)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
