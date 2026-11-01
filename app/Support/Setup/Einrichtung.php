<?php

namespace App\Support\Setup;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * „Einrichtung prüfen“ (Paket 25): Was im echten Betrieb noch fehlt, mit Link zur richtigen Stelle.
 * Jeder Punkt: [Gruppe, Bezeichnung, Zustand ok|offen|optional, Erklärung, Link, Linktext, Pflicht]
 */
class Einrichtung
{
    public const LATEST_PACKAGE = 'paket26';

    private static function count(string $table, ?callable $where = null): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }
            $q = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            if ($where) {
                $where($q);
            }

            return (int) $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function item(string $group, string $label, bool $ok, string $todo, string $done, string $route, string $linkText, bool $required = true, array $params = []): array
    {
        try {
            $url = route($route, $params);
        } catch (Throwable) {
            $url = null;
        }

        return [
            'group' => $group, 'label' => $label, 'state' => $ok ? 'ok' : ($required ? 'offen' : 'optional'),
            'text' => $ok ? $done : $todo, 'url' => $url, 'link' => $linkText, 'required' => $required,
        ];
    }

    public static function checks(): array
    {
        $out = [];
        $today = date('Y-m-d');

        // System
        $installed = self::count('system_updates', fn ($q) => $q->where('status', 'installiert'));
        $lastPkg = Schema::hasTable('system_updates') ? DB::table('system_updates')->where('status', 'installiert')->orderByDesc('id')->value('title') : null;
        $out[] = self::item('System', 'Updates eingespielt', $installed > 0, 'Noch kein Update über die Updates-Seite eingespielt.', 'Zuletzt: '.($lastPkg ?: '–').'.', 'updates.index', 'Updates');
        $noTwo = User::query()->where('is_active', true)->whereNull('two_factor_confirmed_at')->pluck('name')->all();
        $out[] = self::item('System', 'Zwei-Faktor bei allen Benutzern', $noTwo === [], 'Noch nicht eingerichtet bei: '.implode(', ', $noTwo).'. Wird beim nächsten Login verlangt.', 'Alle aktiven Benutzer sind geschützt.', 'users.index', 'Benutzer');
        $key = Settings::get('backup_public_key') !== null;
        $out[] = self::item('System', 'Sicherungsschlüssel hinterlegt', $key, 'Ohne Schlüssel gibt es keine Sicherung.', 'Schlüssel ist hinterlegt.', 'backups.index', 'Sicherungen');
        $lastBackup = null;
        try {
            $lastBackup = \App\Support\Backup\BackupService::lastBackupAt();
        } catch (Throwable) {
        }
        $out[] = self::item('System', 'Sicherung läuft', $lastBackup !== null && time() - $lastBackup < 2 * 86400, $lastBackup ? 'Die letzte Sicherung ist älter als zwei Tage – Cronjob prüfen.' : 'Es gibt noch keine Sicherung.', 'Letzte Sicherung: '.($lastBackup ? date('d.m.Y H:i', $lastBackup) : '–').'.', 'backups.index', 'Sicherungen');
        $lastCron = Schema::hasTable('audit_logs') ? DB::table('audit_logs')->where('event', 'cron.run')->max('created_at') : null;
        $out[] = self::item('System', 'Täglicher Lauf (Cronjob)', $lastCron !== null && strtotime((string) $lastCron) > time() - 36 * 3600, $lastCron ? 'Seit mehr als 36 Stunden nicht gelaufen – Cronjob bei IONOS prüfen.' : 'Noch nie gelaufen – Cronjob bei IONOS mit der Adresse von der Statusseite anlegen.', 'Zuletzt gelaufen: '.($lastCron ? date('d.m.Y H:i', strtotime((string) $lastCron)) : '–').'.', 'status', 'Status');

        // Stammdaten
        $owners = self::count('owners');
        $props = self::count('properties');
        $units = self::count('units');
        $tenants = self::count('tenants');
        $leases = self::count('leases');
        $out[] = self::item('Stammdaten', 'Eigentümer angelegt', $owners > 0, 'Noch kein Eigentümer.', $owners.' Eigentümer.', 'owners.index', 'Eigentümer');
        $out[] = self::item('Stammdaten', 'Objekte und Einheiten', $props > 0 && $units > 0, $props === 0 ? 'Noch kein Objekt.' : 'Objekte ohne Einheiten.', $props.' Objekte, '.$units.' Einheiten.', 'properties.index', 'Objekte');
        $noArea = self::count('units', fn ($q) => $q->where(fn ($w) => $w->whereNull('area_sqm')->orWhere('area_sqm', 0)));
        $out[] = self::item('Stammdaten', 'Wohnflächen eingetragen', $units > 0 && $noArea === 0, $units === 0 ? 'Erst Einheiten anlegen.' : $noArea.' Einheit(en) ohne Fläche – wichtig für Nebenkosten und Rendite.', 'Alle Einheiten haben eine Fläche.', 'properties.index', 'Objekte');
        $out[] = self::item('Stammdaten', 'Mieter und Mietverträge', $tenants > 0 && $leases > 0, $tenants === 0 ? 'Noch keine Mieter (am schnellsten per Excel-Import).' : 'Mieter ohne Mietvertrag.', $tenants.' Mieter, '.$leases.' Mietverträge.', 'import.index', 'Import aus Excel');
        $accounts = self::count('owner_bank_accounts');
        $out[] = self::item('Stammdaten', 'Bankkonto beim Eigentümer', $accounts > 0, 'Kein Bankkonto hinterlegt – wird für Kontoauszüge, Mahnungen und Abrechnungen gebraucht.', $accounts.' Konto/Konten hinterlegt.', 'owners.index', 'Eigentümer');

        // Laufender Betrieb
        $recentImport = self::count('bank_imports', fn ($q) => $q->where('created_at', '>=', now()->subDays(35)));
        $out[] = self::item('Laufender Betrieb', 'Kontoauszüge eingelesen', $recentImport > 0, 'In den letzten 5 Wochen kein Kontoauszug eingelesen – sonst fehlen Mieteingänge.', 'Kontoauszug in den letzten 5 Wochen eingelesen.', 'bank.index', 'Kontoauszüge');
        $rateOk = true;
        try {
            $rateOk = ! \App\Support\Interest\Verzugszinsen::missingCurrentRate($today);
        } catch (Throwable) {
        }
        $out[] = self::item('Laufender Betrieb', 'Basiszinssatz aktuell', $rateOk, 'Der Basiszinssatz für dieses Halbjahr fehlt (Bundesbank, 1.1. und 1.7.).', 'Aktueller Basiszinssatz ist eingetragen.', 'dunning.index', 'Mahnwesen', true);

        // E-Mail
        $mailOk = false;
        try {
            $mailOk = \App\Support\Mail\MailSettings::configured();
        } catch (Throwable) {
        }
        $out[] = self::item('E-Mail', 'E-Mail-Versand eingerichtet', $mailOk, 'Zugangsdaten des Postfachs fehlen (IONOS: smtp.ionos.de, Port 587).', 'Postfach ist eingetragen.', 'mail.settings', 'E-Mail einrichten');
        $testOk = self::count('sent_emails', fn ($q) => $q->where('status', 'gesendet'));
        $out[] = self::item('E-Mail', 'Test-E-Mail erfolgreich', $testOk > 0, 'Noch keine E-Mail erfolgreich gesendet – unter „E-Mail einrichten“ eine Test-E-Mail schicken.', 'E-Mails werden versendet.', 'mail.settings', 'E-Mail einrichten');
        $recvOk = false;
        try {
            $recvOk = \App\Support\Mail\MailSettings::receiveConfigured() && ! \App\Support\Settings::get('imap_last_error') && \App\Support\Settings::get('imap_last_fetch');
        } catch (Throwable) {
        }
        $out[] = self::item('E-Mail', 'E-Mail-Empfang (Posteingang)', (bool) $recvOk, \App\Support\Settings::get('imap_last_error') ? 'Abruf fehlgeschlagen: '.\App\Support\Settings::get('imap_last_error') : 'Noch kein erfolgreicher Abruf – unter „E-Mail einrichten“ den Empfang einschalten und „Verbindung prüfen“.', 'Neue E-Mails kommen alle 5 Minuten in den Posteingang.', 'mail.settings', 'E-Mail einrichten', false);
        $withMail = self::count('tenants', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''));
        $out[] = self::item('E-Mail', 'E-Mail-Adressen der Mieter', $tenants > 0 && $withMail >= $tenants * 0.5, $tenants === 0 ? 'Erst Mieter anlegen.' : 'Nur '.$withMail.' von '.$tenants.' Mietern haben eine E-Mail-Adresse.', $withMail.' von '.$tenants.' Mietern haben eine E-Mail-Adresse.', 'tenants.index', 'Mieter', false);

        // Webseite
        $out[] = self::item('Webseite', 'Impressum und Datenschutz', Settings::imprintComplete(), 'Impressum unvollständig – die Webseite darf so nicht öffentlich sein.', 'Impressum ist ausgefüllt.', 'settings.edit', 'Einstellungen');
        $logo = self::count('site_assets');
        $out[] = self::item('Webseite', 'Webseite gestaltet', $logo > 0 || self::count('settings', fn ($q) => $q->where('key', 'like', 'site\_%')) > 0, 'Logo, Bilder und Texte der Webseite noch nicht angepasst.', 'Webseite ist angepasst.', 'site.edit', 'Webseite gestalten', false);

        // Instandhaltung und Finanzen
        $vendorsMail = self::count('vendors', fn ($q) => $q->where('active', true)->whereNotNull('email')->where('email', '!=', ''));
        $out[] = self::item('Instandhaltung', 'Handwerker mit E-Mail', $vendorsMail > 0, 'Noch kein Handwerker mit E-Mail-Adresse – nötig für Aufträge per Link.', $vendorsMail.' Handwerker/Lieferanten mit E-Mail.', 'vendors.index', 'Lieferanten');
        $propsWithPlan = Schema::hasTable('maintenance_tasks') ? (int) DB::table('maintenance_tasks')->where('active', true)->distinct()->count('property_id') : 0;
        $out[] = self::item('Instandhaltung', 'Wartungsplan je Objekt', $props > 0 && $propsWithPlan >= $props, $props === 0 ? 'Erst Objekte anlegen.' : ($props - $propsWithPlan).' Objekt(e) ohne Wartungsplan (z. B. Rauchmelder, Heizung).', 'Alle Objekte haben einen Wartungsplan.', 'maintenance.index', 'Wartungsplan');
        $insured = Schema::hasTable('documents') ? (int) DB::table('documents')->where('attach_type', 'property')->where('category', 'versicherung')->where('archived', false)->distinct()->count('attach_id') : 0;
        $out[] = self::item('Instandhaltung', 'Versicherung je Objekt abgelegt', $props > 0 && $insured >= $props, $props === 0 ? 'Erst Objekte anlegen.' : ($props - $insured).' Objekt(e) ohne abgelegte Versicherung (mit Ablaufdatum erinnert das CRM rechtzeitig).', 'Bei allen Objekten liegt eine Versicherung.', 'documents.index', 'Dokumente', false);
        $values = Schema::hasTable('property_values') ? (int) DB::table('property_values')->whereNotNull('purchase_price')->count() : 0;
        // Heizung (Paket 26)
        $withFuel = self::count('nk_settings', fn ($q) => $q->whereNotNull('fuel')->whereIn('property_id', DB::table('properties')->whereNull('deleted_at')->select('id')));
        $out[] = self::item('Heizung', 'Brennstoff je Objekt', $props > 0 && $withFuel >= $props, $props === 0 ? 'Erst Objekte anlegen.' : ($props - $withFuel).' Objekt(e) ohne Angabe zur Heizung – nötig für die CO2-Kostenaufteilung in der Nebenkostenabrechnung (Objekt → Nebenkosten).', 'Bei allen Objekten angegeben.', 'properties.index', 'Objekte');
        $heatMeters = self::count('meters', fn ($q) => $q->where('active', true)->whereIn('kind', ['warmwasser', 'waerme', 'hkv']));
        $notRemote = self::count('meters', fn ($q) => $q->where('active', true)->whereIn('kind', ['warmwasser', 'waerme', 'hkv'])->where('remote', false));
        if ($heatMeters > 0) {
            $out[] = self::item('Heizung', 'Zähler fernablesbar (bis 31.12.2026)', $notRemote === 0, $notRemote.' von '.$heatMeters.' Zählern für Heizung/Warmwasser sind nicht als fernablesbar markiert. Ab 2027 dürfen Mieter sonst 3 % der Heizkosten kürzen.', 'Alle '.$heatMeters.' Zähler sind fernablesbar.', 'consumption.index', 'Zähler und CO2');
        }

        $out[] = self::item('Finanzen', 'Kaufpreis und Wert je Objekt', $props > 0 && $values >= $props, $props === 0 ? 'Erst Objekte anlegen.' : ($props - $values).' Objekt(e) ohne Kaufpreis – nötig für die Rendite.', 'Bei allen Objekten eingetragen.', 'finance.index', 'Finanzierung', false);
        $loans = self::count('loans');
        $out[] = self::item('Finanzen', 'Darlehen eingetragen', $loans > 0, 'Noch kein Darlehen – falls Objekte finanziert sind, hier eintragen (Zinsen gehen dann in die Anlage V).', $loans.' Darlehen.', 'finance.index', 'Finanzierung', false);
        $out[] = self::item('Finanzen', 'DATEV-Konten mit Steuerberater', Settings::get('datev_accounts_confirmed') === '1', 'Erlöskonten (SKR04) und Buchung von Zinsen/Gebühren mit dem Steuerberater klären, dann hier abhaken.', 'Mit dem Steuerberater abgestimmt.', 'setup.index', 'abhaken unten', false);

        return $out;
    }

    /** [erledigt, Pflicht offen, gesamt Pflicht] */
    public static function summary(): array
    {
        $c = collect(self::checks());
        $req = $c->where('required', true);

        return ['done' => $req->where('state', 'ok')->count(), 'open' => $req->where('state', 'offen')->count(), 'total' => $req->count()];
    }
}
