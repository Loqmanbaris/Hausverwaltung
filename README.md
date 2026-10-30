# Baris Hausverwaltung – CRM

Laravel-Anwendung für baris-verwaltung.de (Live) und test.baris-verwaltung.de (Test).

**Dieses Repository muss privat bleiben.** Es enthält keine Passwörter und keinen Signierschlüssel, aber den gesamten Programmcode.

## Zwei Wege, Neues auf den Server zu bringen

1. **Per Git (`auto-deploy.sh`, `deploy.sh`):** Ein Cronjob prüft alle 5 Minuten, ob es auf GitHub einen neuen Stand des Zweigs gibt, in dem der Server steht, und spielt ihn ein: Sicherung, Wartungsmodus, Code, Migrationen, Selbsttests, online. Bei einem Fehler wird der alte Code wiederhergestellt. Das Ergebnis steht im Zweig `status-main` bzw. `status-test` (`protokoll.txt`).
2. **Per Paket (`.crmupdate`) über Einstellungen → Updates im CRM:** Signierte Pakete werden hochgeladen oder aus GitHub geholt. Sie ändern Dateien an Git vorbei.

**Beide Wege nicht mischen.** `deploy.sh` bricht ab, wenn auf dem Server Programmdateien direkt geändert wurden (das tun Pakete). Wer Git nutzt, spielt keine Pakete über die Weboberfläche ein und umgekehrt.

## Zweige

| Zweig | Bedeutung |
|---|---|
| `main` | Stand, den der Live-Server (`~/hausverwaltung/app`) automatisch einspielt |
| `test` | Stand für den Testserver (`~/crm-test/app`), derzeit nicht in Benutzung |
| `status-main`, `status-test` | Protokolle der automatischen Einspielung |
| `stand-paket-47` | Vollständiger Programmstand bis Paket 47, Paket für Paket als Commits. Löst nichts aus, solange kein Server auf diesem Zweig steht |

## Stand

Der Zweig `stand-paket-47` baut auf dem Programmstand vom 30.10.2026 (nach Paket 39) auf. Paket 40 bis 47 folgen als einzelne Commits, in der Reihenfolge, in der sie eingespielt werden. Was jedes Paket ändert, steht in `CHANGELOG.md`.

`bash tools/stand-pruefen.sh` zeigt (nur lesend) auf dem Server, welchem dieser Commits der Server entspricht.

## Nicht im Repository

`vendor/` (Bibliotheken, liegen auf dem Server), `storage/` (Daten und Belege), `.env` (Zugangsdaten), Sicherungen und der Signierschlüssel für Pakete (`update-private.pem`).

## Sicherheit

* Repository **privat** lassen. Tokens nur mit Zugriff auf dieses eine Repository und kurzer Laufzeit.
* Nie hochladen: `.env`, `update-private.pem` und andere `*.pem`-Dateien. Die `.gitignore` sperrt sie.
* `tools/build-update.php` baut und unterschreibt ein Paket, braucht dafür aber den privaten Schlüssel, der nicht hier liegt.
