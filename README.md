# Baris Hausverwaltung – CRM

Laravel-Anwendung für baris-verwaltung.de (Live) und test.baris-verwaltung.de (Test).

## Updates

| Zweig  | Server                  | Ordner auf IONOS          |
|--------|-------------------------|---------------------------|
| `test` | test.baris-verwaltung.de | `~/crm-test/app`         |
| `main` | baris-verwaltung.de      | `~/hausverwaltung/app`   |

1. Änderungen kommen zuerst in `test`.
2. Der Server prüft alle 5 Minuten (`auto-deploy.sh` per Cronjob) und spielt Neues mit `deploy.sh` ein:
   Sicherung → Wartungsmodus → Code → Migrationen → Selbsttests → online.
   Bei einem Fehler wird automatisch der alte Code wiederhergestellt.
3. Das Ergebnis steht im Zweig `status-test` bzw. `status-main` (Datei `protokoll.txt`).
4. Passt alles auf dem Testserver, wird `test` nach `main` übernommen.

Von Hand: `bash deploy.sh` (einspielen) oder `bash deploy.sh --selbsttest` (nur prüfen).

Keine `.crmupdate`-Pakete mehr über die Weboberfläche einspielen – sie ändern Dateien an Git vorbei.
