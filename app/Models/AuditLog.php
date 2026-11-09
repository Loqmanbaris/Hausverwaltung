<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Änderungsprotokoll. Einträge werden nur angelegt, nie geändert oder gelöscht.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public const LABELS = [
        'login.success' => 'Angemeldet',
        'login.password_ok' => 'Passwort korrekt',
        'login.failed' => 'Anmeldung fehlgeschlagen',
        'login.locked' => 'Konto gesperrt (Fehlversuche)',
        'login.blocked' => 'Anmeldung abgewiesen',
        'logout' => 'Abgemeldet',
        'two_factor.enabled' => 'Zwei-Faktor eingerichtet',
        'two_factor.failed' => 'Falscher Code',
        'two_factor.locked' => 'Konto gesperrt (falsche Codes)',
        'two_factor.recovery_used' => 'Wiederherstellungscode benutzt',
        'two_factor.reset' => 'Zwei-Faktor zurückgesetzt',
        'password.changed' => 'Passwort geändert',
        'password.reset' => 'Passwort zurückgesetzt',
        'user.created' => 'Benutzer angelegt',
        'user.blocked' => 'Benutzer gesperrt',
        'user.mail_all' => 'Recht „Alle E-Mails“ geändert',
        'user.unblocked' => 'Benutzer entsperrt',
        'owner.viewed' => 'Eigentümer geöffnet',
        'owner.created' => 'Eigentümer angelegt',
        'owner.updated' => 'Eigentümer geändert',
        'owner.deleted' => 'Eigentümer gelöscht',
        'owner.access_updated' => 'Freigaben für Mitarbeiter geändert',
        'bank_account.created' => 'Bankverbindung hinzugefügt',
        'bank_account.deleted' => 'Bankverbindung entfernt',
        'property.created' => 'Objekt angelegt',
        'property.updated' => 'Objekt geändert',
        'property.deleted' => 'Objekt gelöscht',
        'unit.created' => 'Einheit angelegt',
        'unit.updated' => 'Einheit geändert',
        'unit.deleted' => 'Einheit gelöscht',
        'tenant.created' => 'Mieter angelegt',
        'tenant.updated' => 'Mieter geändert',
        'tenant.deleted' => 'Mieter gelöscht',
        'tenant_contact.created' => 'Ansprechpartner hinzugefügt',
        'tenant_contact.deleted' => 'Ansprechpartner entfernt',
        'settings.updated' => 'Einstellungen geändert',
        'backup.created' => 'Sicherung erstellt',
        'backup.failed' => 'Sicherung fehlgeschlagen',
        'backup.downloaded' => 'Sicherung heruntergeladen',
        'backup.key_set' => 'Schlüssel für Sicherungen gespeichert',
        'receipt.created' => 'Beleg erfasst',
        'receipt.updated' => 'Beleg geändert',
        'receipt.deleted' => 'Beleg archiviert',
        'document.downloaded' => 'Beleg-Datei heruntergeladen',
        'document.failed' => 'Beleg-Datei nicht lesbar oder verändert',
        'cron.run' => 'Tägliche Aufgaben ausgeführt',
        'cron.failed' => 'Tägliche Aufgaben fehlgeschlagen',
        'cron.rejected' => 'Cron-Adresse mit falschem Schlüssel',
        'vendor.created' => 'Lieferant angelegt',
        'vendor.updated' => 'Lieferant geändert',
        'cost_type.created' => 'Kostenart angelegt',
        'cost_type.updated' => 'Kostenart geändert',
        'recurring.created' => 'Wiederkehrende Ausgabe angelegt',
        'recurring.updated' => 'Wiederkehrende Ausgabe geändert',
        'datev.settings' => 'DATEV-Einstellungen geändert',
        'datev.accounts' => 'DATEV-Konten geändert',
        'datev.exported' => 'DATEV-Export erstellt',
        'datev.downloaded' => 'DATEV-Datei heruntergeladen',
        'datev.receipts_downloaded' => 'Belege zum DATEV-Export heruntergeladen',
        'lease.created' => 'Mietvertrag angelegt',
        'lease.updated' => 'Mietvertrag geändert',
        'lease.deleted' => 'Mietvertrag gelöscht',
        'lease.rent_added' => 'Mietänderung erfasst',
        'lease.rent_removed' => 'Mietänderung entfernt',
        'lease.index_base' => 'Index-Ausgangswert geändert',
        'rent_payment.created' => 'Mietzahlung erfasst',
        'rent_payment.deleted' => 'Mietzahlung storniert',
        'bank.imported' => 'Kontoauszug eingelesen',
        'bank.import_deleted' => 'Kontoauszug gelöscht',
        'bank.assigned' => 'Kontoumsatz zugeordnet',
        'bank.ignored' => 'Kontoumsatz als „keine Miete“ markiert',
        'bank.reset' => 'Zuordnung eines Kontoumsatzes zurückgesetzt',
        'bank.edited' => 'Kontoumsatz bearbeitet',
        'bank.payment_changed' => 'Zuordnung eines Kontoumsatzes geändert',
        'bank.payment_removed' => 'Zuordnung eines Kontoumsatzes entfernt',
        'bank.payer_removed' => 'Zahlerkonto entfernt',
        'datev.rent_accounts' => 'Erlöskonten Mieten geändert',
        'datev.rent_exported' => 'DATEV-Export Mieten erstellt',
        'update.installed' => 'Update eingespielt',
        'update.failed' => 'Update fehlgeschlagen',
        'update.rolled_back' => 'Update zurückgenommen',
        'update.rejected' => 'Update-Paket abgelehnt',
        'nk.created' => 'Nebenkostenabrechnung angelegt',
        'nk.finalized' => 'Nebenkostenabrechnung abgeschlossen',
        'nk.reopened' => 'Nebenkostenabrechnung wieder geöffnet',
        'nk.deleted' => 'Nebenkostenabrechnung gelöscht',
        'nk.settings' => 'Nebenkosten-Einstellungen geändert',
        'meter.created' => 'Zähler angelegt',
        'meter.deleted' => 'Zähler entfernt',
        'meter.readings' => 'Zählerstände erfasst',
        'lease.persons' => 'Personenzahl erfasst',
        'anlage_v.exported' => 'Anlage V heruntergeladen',
        'update.source_exported' => 'Programmstand heruntergeladen',
        'bank.receipt_linked' => 'Abbuchung mit Beleg verknüpft',
        'bank.no_receipt' => 'Abbuchung ohne Beleg markiert',
        'bank.receipt_reset' => 'Belegzuordnung zurückgesetzt',
        'dunning.created' => 'Mahnung erstellt',
        'dunning.cancelled' => 'Mahnung storniert',
        'dunning.settings' => 'Einstellungen Mahnwesen geändert',
        'listing.created' => 'Inserat angelegt',
        'listing.updated' => 'Inserat geändert',
        'listing.online' => 'Inserat online gestellt',
        'listing.offline' => 'Inserat offline genommen',
        'listing.deleted' => 'Inserat gelöscht',
        'booking.created' => 'Belegung eingetragen',
        'booking.deleted' => 'Belegung entfernt',
        'inquiry.deleted' => 'Anfrage gelöscht',
        'cron.token_renewed' => 'Neue Cron-Adresse erzeugt',
        'contract.created' => 'Mietvertrag erstellt',
        'contract.signed' => 'Mietvertrag am Tablet unterschrieben',
        'contract.scan' => 'Unterschriebener Mietvertrag abgelegt',
        'contract.cancelled' => 'Mietvertrag storniert',
        'contract.deleted' => 'Mietvertrag-Entwurf gelöscht',
        'contract.template' => 'Vertragsvorlage geändert',
        'handover.created' => 'Übergabeprotokoll angelegt',
        'handover.finalized' => 'Übergabeprotokoll abgeschlossen',
        'handover.deleted' => 'Übergabeprotokoll gelöscht',
        'interest.booked' => 'Verzugszinsen gebucht',
        'interest.cancelled' => 'Zinsbuchung storniert',
        'interest.base_rate' => 'Basiszinssatz geändert',
        'installments.created' => 'Ratenvereinbarung angelegt',
        'installments.fulfilled' => 'Ratenvereinbarung erfüllt',
        'installments.erfuellt' => 'Ratenvereinbarung als erfüllt markiert',
        'installments.geplatzt' => 'Ratenvereinbarung nicht eingehalten',
        'installments.storniert' => 'Ratenvereinbarung storniert',
        'installments.aktiv' => 'Ratenvereinbarung wieder aktiv',
        'import.done' => 'Excel-Import',
        'site.updated' => 'Webseite geändert',
        'mail.sent' => 'E-Mail gesendet',
        'mail.failed' => 'E-Mail nicht gesendet',
        'mail.settings' => 'E-Mail-Einstellungen geändert',
        'document.created' => 'Dokument abgelegt',
        'document.updated' => 'Dokument geändert',
        'document.deleted' => 'Dokument gelöscht',
        'loan.created' => 'Darlehen angelegt',
        'loan.updated' => 'Darlehen geändert',
        'loan.deleted' => 'Darlehen gelöscht',
        'loan.event' => 'Darlehen: Eintrag',
        'loan.payment' => 'Abbuchung Darlehen zugeordnet',
        'loan.payment_reset' => 'Darlehenszuordnung zurückgesetzt',
        'property.values' => 'Kaufpreis und Wert geändert',
        'workorder.created' => 'Schadensfall angelegt',
        'workorder.assigned' => 'Auftrag vergeben',
        'workorder.done' => 'Auftrag erledigt',
        'workorder.billed' => 'Auftrag abgerechnet',
        'workorder.updated' => 'Schadensfall geändert',
        'maintenance.done' => 'Wartung erledigt',
        'maintenance.changed' => 'Wartung geändert',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function eventLabel(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }

    /** Ereignis-Schlüssel, deren deutsche Bezeichnung den Suchbegriff enthält. */
    public static function eventsMatching(string $term): array
    {
        $term = mb_strtolower($term);

        return array_keys(array_filter(
            self::LABELS,
            fn (string $label) => str_contains(mb_strtolower($label), $term)
        ));
    }
}
