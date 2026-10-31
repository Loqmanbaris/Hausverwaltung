# Baris Hausverwaltung – CRM (baris-verwaltung.de)

Verwaltung der eigenen Immobilien: Stammdaten, Mieten, Belege, Bank, DATEV, Nebenkosten, Verträge, Mieterportal, Webseite.
Laravel 13, PHP 8.4, IONOS-Webspace (`~/hausverwaltung/app`). Oberfläche, Texte und Kommentare auf Deutsch.
Siehe auch `README.md` und `CHANGELOG.md`.

## Wichtigste Regel: `main` geht automatisch live
- Cronjob `2-59/5` startet `auto-deploy.sh`: neuer Stand auf `main` → `deploy.sh`: Sicherung → Wartungsmodus → Code →
  `composer install` (nur bei geändertem `composer.lock`) → `migrate` → Caches → alle `*:selbsttest`-Befehle → online.
  Bei Fehler automatisch alter Stand. Ein fehlgeschlagener Stand wird nicht noch einmal versucht.
- Ergebnis jedes Laufs steht im Zweig `status-main`, Datei `protokoll.txt` – nach einem Push dort nachsehen:
  `git fetch origin status-main && git show FETCH_HEAD:protokoll.txt`
- Nur getestete Änderungen auf `main`. Zweig `test` (Testserver `~/crm-test/app`) ist derzeit nicht in Benutzung.
- **Nie Git und Update-Pakete (`.crmupdate`, Einstellungen → Updates) mischen.** Der Server läuft über Git. Pakete würden Dateien
  an Git vorbei ändern und `deploy.sh` bricht dann ab.
- Auf IONOS ist `php` die CGI-Version: immer `php8.4-cli`.

## Vor jedem Commit
- Braucht PHP 8.4 (Laravel 13). Mindestens `php -l` auf allen geänderten Dateien.
- Neue Bereiche bekommen einen eigenen `bereich:selbsttest`-Befehl (siehe `app/Console/Commands/*Selbsttest.php`); er läuft
  bei jedem Einspielen und verhindert kaputte Stände.
- Migrationen: `JJJJ_MM_TT_000001_beschreibung.php`, Datum fortlaufend nach der neuesten Migration.
- Routen liegen nach Bereich getrennt in `routes/*.php`, jeweils mit `SecurityHeaders`, `auth`, `EnsureTwoFactor`
  (Verwaltungsbereiche zusätzlich `EnsureAdmin`).
- Änderungen protokollieren mit `App\Support\Audit::log(...)`.

## Anmeldung
- E-Mail + Passwort, Zwei-Faktor ist Pflicht (`EnsureTwoFactor`, Sitzungswert `two_factor_passed`).
- Gemeinsamer Zugang aus dem Telemaster-Portal: `GET /sso?token=…` (`PortalSsoController`). Signierter Sprung-Schein (HMAC-SHA256,
  60 s, einmalig), Schlüssel `PORTAL_SSO_SECRET` in `.env` (= `SSO_HAUSVERWALTUNG_SECRET` im Portal). Das Portal lässt den Sprung
  nur mit Authenticator zu, deshalb gilt der zweite Faktor hier als bestanden. Format mit Repo `Portal` (`App\Support\Sso\SsoLink`) gleich halten.

## Nie ins Repository
`.env`, `update-private.pem` und andere `*.pem`, `storage/`, `vendor/`, Sicherungen. Repository bleibt privat.

## Zusammenarbeit
- Der Inhaber arbeitet meist vom iPad/Handy: Befehle für den Server einzeln in eigenen Code-Blöcken, mit erwartetem Ergebnis.
- Nicht zwei Personen gleichzeitig an dieser Seite. Vor der Arbeit `git pull`.
- Commit-Nachrichten auf Deutsch; größere Änderungen zusätzlich in `CHANGELOG.md`.
