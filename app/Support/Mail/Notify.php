<?php

namespace App\Support\Mail;

use App\Models\Inquiry;
use Throwable;

/** Hinweise an die Verwaltung per E-Mail (Paket 21). Fehler dürfen den Ablauf nie stören. */
class Notify
{
    public static function newInquiry(Inquiry $q): void
    {
        try {
            if (MailService::$fake === null && ! MailSettings::configured()) {
                return;
            }
            if (MailSettings::get('mail_notify') !== '1') {
                return;
            }
            $to = MailSettings::copyAddress();
            if (! MailService::validAddress($to)) {
                return;
            }
            $what = match ($q->topic) {
                'schaden' => 'Schadensmeldung',
                default => $q->listing_id ? 'Anfrage zu einem Inserat' : 'Anfrage über das Kontaktformular',
            };
            $body = "Neue {$what} über die Webseite.\n\n"
                .'Von: '.$q->name.($q->company ? ' ('.$q->company.')' : '')."\n"
                .'E-Mail: '.$q->email."\n"
                .($q->phone ? 'Telefon: '.$q->phone."\n" : '')
                .($q->date_from ? 'Zeitraum: '.$q->date_from->format('d.m.Y').' bis '.$q->date_to?->format('d.m.Y')."\n" : '')
                ."\n".$q->message."\n\n"
                .'Im CRM ansehen und antworten: '.route('inquiries.show', $q);
            MailService::send([$to], $what.' von '.$q->name, $body, [], ['kind' => 'hinweis', 'ref_id' => $q->id, 'owner_id' => $q->owner_id], null, false);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
