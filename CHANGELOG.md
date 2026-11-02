# Änderungsverlauf

Neueste Pakete zuerst. Die Beschreibung steht auch im CRM beim jeweiligen Paket.

## Stammdaten und Webseite unter Einstellungen, Excel-Import entfernt
1. Die Gruppen „Stammdaten“ (Eigentümer, Objekte und Einheiten, Mieter, Dokumente) und „Webseite“ (Inserate, Anfragen, Webseite gestalten, Webseite ansehen) stehen nicht mehr im Seitenmenü, sondern als Kacheln unter Profilmenü → Einstellungen. Zahlen für neue Anfragen und abgelaufene Dokumente stehen an den Kacheln und als Punkt am Profilzeichen.
2. „Import aus Excel“ (Paket 15) ist gelöscht: Seite, Vorlage, Selbsttest und Knopf auf der Übersicht. Schon übernommene Daten bleiben unverändert.
Keine Migration.

## Kontoumsätze bearbeiten
1. Jeder Umsatz unter Kontoauszüge → Umsätze (und unter Ausgaben abgleichen) hat jetzt „Bearbeiten“.
2. Angaben korrigieren: Buchungstag, Wertstellung, Betrag, Auftraggeber/Empfänger, Buchungstext, Verwendungszweck. Zugeordnete Zahlungen übernehmen einen neuen Buchungstag; ein Betrag unter dem schon Zugeordneten wird abgelehnt. Wechselt das Vorzeichen, wird aus der Gutschrift eine Abbuchung (und umgekehrt).
3. Zuordnungen einzeln ändern (anderer Mietvertrag, anderer Betrag) oder einzeln entfernen, ohne alles zurückzusetzen. Rest direkt dort zuordnen, „keine Miete“ mit Grund ändern oder wieder zur Miete machen.
4. Hinweis, wenn eine Zahlung schon an DATEV übergeben wurde. Jede Änderung steht im Protokoll (mit alten und neuen Werten).
Keine Migration. Selbsttest: mieten:selbsttest

## Aussehen wie im Telemaster-Portal
1. Kopfleiste und Seitenmenü in Anthrazit wie im Portal, Schrift Roboto (liegt auf dem eigenen Server, keine Verbindung zu Google).
2. Jeder Menüpunkt hat ein Symbol. Der aktive Punkt ist in der Farbe der Hausverwaltung hervorgehoben (Akzentfarbe aus „Webseite gestalten“, sonst Orange), nicht im Gold des Portals.
3. Karten mit runden Ecken und feinem Rahmen, Suchfeld und Profilmenü passend zur dunklen Kopfleiste.
Keine Migration. Schrift: Roboto (Apache-2.0), public/fonts.

## E-Mail in Outlook-Ansicht
1. „E-Mails“ ist jetzt aufgebaut wie Outlook (gleich wie bei Freytag): Ordner Posteingang, Gesendet, Archiv | Liste | Lesebereich. Am großen Bildschirm drei Spalten, am iPad zwei, am Handy nacheinander mit „Zurück“.
2. Liste nach Heute, Gestern, Diese Woche … gruppiert, farbige Namenskürzel, ungelesene fett mit Balken, Zeichen für Anhang und beantwortet, Marke „Mieter“/„Lieferant“.
3. HTML-E-Mails werden wie im Original angezeigt (abgeschotteter Rahmen ohne Skripte), eingebettete Bilder ja, Bilder aus dem Internet nur auf Wunsch („Bilder anzeigen“). Schon abgeholte E-Mails holen ihre HTML-Fassung beim ersten Öffnen nach.
4. Antworten, Allen antworten (mit Cc), Weiterleiten und Neue E-Mail direkt im Lesebereich, mit Cc-Feld, Anhängen und Schutz vor versehentlichem Verwerfen.
5. „Gesendet“ zeigt alle aus dem CRM verschickten E-Mails (auch Mahnungen, Abrechnungen usw.), Fehler rot markiert.
Neue Spalte received_emails.body_html (Migration). Selbsttest: posteingang:selbsttest

## Einstellungen als Kacheln im Profilmenü
1. Das runde Zeichen oben rechts öffnet jetzt ein Profilmenü (wie im Portal und bei Freytag): Einstellungen, Passwort ändern, App installieren, Abmelden.
2. Neue Seite „Einstellungen“ (/profil/einstellungen) mit Kacheln: Mein Konto (Passwort, Startseite anpassen, Kalender-Abo, „Mein Tag“ per E-Mail), Dieses Gerät (App installieren, Menü-Ansicht), Andere Systeme (Telemaster-Portal, Webseite), für Administratoren Verwaltung (Benutzer, Allgemeine Einstellungen, E-Mail einrichten, Webseite gestalten, Vertragsvorlagen, DATEV) und System (Einrichtung prüfen, Sicherungen, Löschfristen, Updates, Status, Protokoll).
3. Die Gruppe „Verwaltung“ im Seitenmenü entfällt dafür. Offene Einrichtungsschritte zeigt ein Punkt am Profilzeichen.
Keine Migration. Selbsttest: design:selbsttest

## E-Mail-Empfang (Posteingang)
1. Neuer Posteingang unter „E-Mails“: Das CRM holt alle 5 Minuten neue E-Mails aus dem Postfach (IMAP, IONOS: imap.ionos.de, Port 993), mit gleichem Benutzernamen und Passwort wie für den Versand. Zusätzlich „Jetzt abrufen“. Beim ersten Abruf die E-Mails der letzten 30 Tage.
2. Im Postfach bleibt alles unverändert (nur lesen, E-Mails bleiben dort ungelesen). Blindkopien, die das CRM selbst verschickt, erscheinen nicht im Posteingang.
3. Lesen mit Anhängen, Antworten (mit Bezug, landet beim Empfänger im selben Verlauf), Weiterleiten mit Anhängen, Archivieren, als ungelesen markieren, Suche. HTML-E-Mails werden nur als Text gezeigt.
4. Absender werden Mietern (auch Ansprechpartnern), Eigentümern, Lieferanten und Interessenten zugeordnet. Mitarbeiter sehen nur E-Mails ihrer Eigentümer. Beim Mieter: Link „Empfangene E-Mails“.
5. Neu: „Neue E-Mail“ – freie E-Mail an beliebige Adressen mit Anhängen.
6. E-Mail einrichten: neuer Abschnitt „Posteingang“ und „Verbindung prüfen“. Ungelesene E-Mails als Zahl im Menü.
Neue Tabelle received_emails (Migration), Anhänge unter storage/app/private/posteingang. Abruf: `mail:abrufen` (läuft in auto-deploy.sh). Selbsttest: posteingang:selbsttest

## Paket 47: Beleg-Scanner (Zuschneiden, PDF, Texterkennung)
Beim Erfassen eines Belegs (Belege → Neuer Beleg, Foto aufnehmen oder Datei auswählen):
1. Zuschneiden: Nach dem Foto öffnet sich ein Dialog. Die Kanten des Belegs werden automatisch gesucht, du ziehst die vier Ecken mit dem Finger nach. Das Bild wird entzerrt und begradigt. Dazu Drehen, „Ganzes Bild“ und die Darstellung Kontrast, Original oder Schwarzweiß.
2. PDF: Die Seiten werden beim Speichern zu einer PDF zusammengefasst (abschaltbar), oder sofort mit „Jetzt als PDF zusammenfassen“. Nach der Texterkennung ist die PDF durchsuchbar (unsichtbarer Text unter dem Bild).
3. Texterkennung (OCR) auf Deutsch: Sie läuft im Browser, es werden keine Bilder an Dritte gesendet. Sie trägt Lieferant, Datum, Betrag und Rechnungsnummer in leere Felder ein (grün markiert, bitte prüfen) und schlägt die Kostenart des Lieferanten vor.
4. Neues Feld „Rechnungsnummer“ im Beleg. Sie steht im DATEV-Export als Belegnummer.
Enthält Tesseract.js 5.1.1 und tesseract.js-core 5.1.0 (Apache-2.0), deutsche Sprachdaten tessdata_best_int (Apache-2.0). Keine Migration. Selbsttest: scan:selbsttest
Hinweis: Das Paket ist groß (mehrere Megabyte), weil die Texterkennung mitgeliefert wird. Voraussetzung: Paket 45.

## Paket 46: Planvorlagen in „Mein Tag“
Neue Karte „Vorlagen für deinen Plan“ ganz unten in „Mein Tag“. Mit einem Tipp übernimmst du fertige Listen in deinen eigenen Plan:
1. DATEV einrichten (mit Steuerberater): Anruf beim Steuerberater, Gegenkonto und Konten im CRM eintragen, Probe-Export prüfen lassen (5 Einträge).
2. Wochenroutine Hausverwaltung: Kontoauszüge, Schäden und Aufträge, Belege, Wochenabschluss jede Woche; Mahnlauf jeden Monat am zweiten Montag; Basiszinssatz alle 6 Monate (6 Einträge, mit Erinnerung).
3. Nebenkostenabrechnung 2025: Schritte bis zur Frist 31.12.2026 (4 Einträge).
Einträge in der Vergangenheit werden übersprungen. Was es mit gleichem Titel schon gibt, wird nicht doppelt angelegt. Keine Migration. Voraussetzung: Paket 44. Selbsttest: plan:selbsttest

## Paket 45: Rechnungen schreiben (Hausverwaltung als Aussteller)
1. Neue Seite „Rechnungen schreiben“ (nur Administratoren): freie Rechnungen an Mieter, Eigentümer (z. B. Verwaltungshonorar) und sonstige Kunden, auch an Montage-Gäste. Positionen mit Menge, Einheit, Einzelpreis und Steuersatz (19 %, 7 %, 0 % mit Grund der Steuerbefreiung), Leistungsdatum oder -zeitraum, Zahlungsziel, Referenz, Text.
2. Rechnungsdaten der Hausverwaltung (Name, Anschrift, Steuernummer oder USt-ID, IBAN mit Prüfsumme, Zahlungsziel, Regelbesteuerung oder Kleinunternehmer § 19 UStG), vorbelegt aus dem Impressum.
3. Pflichtangaben nach § 14 Abs. 4 UStG werden vor dem Festschreiben geprüft und in verständlichen Worten gemeldet. Festschreiben vergibt die fortlaufende Nummer (RE-Jahr-0001), friert Aussteller und Beträge ein; danach keine Änderung und kein Löschen.
4. Bei Rechnungen an Unternehmen entsteht zusätzlich die E-Rechnung (XRechnung 3.0, XML), gegen das Schema geprüft. Privatpersonen bekommen das PDF.
5. PDF und lesbare Ansicht, Versand per E-Mail mit PDF und XML, als bezahlt oder versendet markieren, Stornieren über Gutschrift (GS-Jahr-0001).
Die Mietrechnungen an Gewerbemieter bleiben unter „E-Rechnung (Mieten)“. Neue Tabellen invoices und invoice_lines (Migration). Voraussetzung: Paket 44. Selbsttest: rechnung:selbsttest
Hinweis: Die KoSIT-Geschäftsregeln (Schematron) prüft das CRM nicht. Vor dem ersten Einsatz bitte mit einem Prüfdienst testen. Keine Rechts- oder Steuerberatung.

## Paket 44: Wiederkehrende Einträge in „Mein Tag“
1. Eigene Termine und To-dos lassen sich wiederholen: jede Woche, alle 2 Wochen, jeden Monat (gleiches Datum), jeden Monat am gleichen Wochentag (z. B. zweiter Montag), alle 6 Monate am gleichen Wochentag, jedes Jahr. Optional mit Enddatum.
2. Serien erscheinen in Tages- und Wochenansicht mit Markierung „↻“. Bei Serien werden einzelne Tage erledigt oder ausgelassen („diesen Tag auslassen“), die Serie läuft weiter. Bearbeiten und Löschen gelten für die ganze Serie.
3. Erinnerungen und die Zahl „To-dos offen“ berücksichtigen Serien. Ein verpasster Tag einer Serie wird nicht als überfällig angehäuft.
4. Kalender-Abo: Serien erscheinen als ein Termin mit Wiederholung (RRULE), ausgelassene und erledigte Tage als Ausnahme.
Neue Spalten in personal_items und neue Tabelle personal_item_marks (Migration). Voraussetzung: Paket 43. Selbsttest: serie:selbsttest

## Paket 43: Mein Tag im neuen Look, App installieren
1. „Mein Tag“ für das Handy: Schnellwahl (Mein Tag, Kalender, Aufträge, Mehr), dunkler Kopfbereich mit Begrüßung, Datum, Kalenderwoche, Ansichtswahl Tag/Woche, Vor/Zurück und Fortschritt (erledigt, To-dos offen), darunter farbige Zähl-Kacheln (Termine heute, To-dos offen, Fristen diese Woche, Mahnungen vorgeschlagen, Neue Anfragen, Schäden gemeldet, Kontoumsätze offen). Jede Kachel führt zur passenden Seite. Runder Plus-Knopf für neue Einträge.
2. App installieren: Das CRM lässt sich als App auf den Startbildschirm legen (Manifest, Symbol, Offline-Seite). Der Knopf „App installieren“ steht im Kopfbereich von „Mein Tag“ und im Menü. Die App speichert keine Daten offline.
Keine Migration. Voraussetzung: Paket 42. Selbsttest: pwa:selbsttest

## Paket 42: Mein Tag, eigene Termine und To-dos, Menü-Ansichten
1. Neue Seite „Mein Tag“: Tages- und Wochenansicht mit eigenen Terminen und To-dos (Tag und Uhrzeit, bis-Uhrzeit, Notiz) und den automatischen Fristen aus dem CRM (Mahnungen, Wartung, Verträge, Mieterhöhungen usw.). Überfällige To-dos stehen oben, erledigen per Knopf.
2. Erinnerungen: wahlweise am Morgen des Tages, zur Uhrzeit, 15 Minuten, 1 Stunde oder 1 Tag vorher. Sie erscheinen als Hinweis in „Mein Tag“ und als Alarm im Kalender-Abo (Handy-Kalender). Optional schickt der tägliche Lauf morgens „Mein Tag“ per E-Mail (nur wenn etwas ansteht). Eine E-Mail zur Minute ist mit dem täglichen Lauf bei IONOS nicht möglich.
3. Kalender-Abo: Eigene Einträge erscheinen im Abo (neue Art „Meine Termine und To-dos“, mit Alarm und Endzeit).
4. Menü: Umschalter „Alltag“ / „Verwaltung“ ganz oben (wird pro Gerät gemerkt). „Alltag“ zeigt nur Tägliches, „Verwaltung“ alles. Neuer Menüpunkt „Mein Tag“ mit Zahl offener To-dos.
Neue Tabelle personal_items und Spalte users.day_mail (Migration). Selbsttest: tag:selbsttest

## Paket 41: Heizungsumlage und Wärmepumpe (Stand Gesetz vom 23.07.2026, in Kraft seit 29.07.2026)
1. Mieterhöhung nach Modernisierung: Auswahl der Heizungsart (Wärmepumpe, Fernwärme/erneuerbar, Gas oder Öl nach § 43 GModG).
2. § 559e Abs. 2 BGB: Bei geförderter erneuerbarer Heizung wird der Erhaltungsanteil automatisch mit 15 % gerechnet. Bei Gas oder Öl entfällt der Pauschalabzug, der Anteil der tatsächlich ersparten Kosten wird eingetragen.
3. § 559f BGB (neu): Bei Wärmepumpen zählen ohne Nachweis einer Jahresarbeitszahl von mindestens 2,5 (oder Ausnahme) nur die halben Kosten. Auswahl im Formular; Rechenweg im Schreiben zeigt die Halbierung.
Ältere Erfassungen ohne Heizungsart rechnen wie bisher. Keine Migration. Selbsttest: umlage:selbsttest. Keine Rechtsberatung; Mustertexte bitte prüfen lassen.

## Paket 40: Kleine Korrekturen
1. Kaution: „Abrechnung zurücknehmen“ entfernt nur noch die Buchungen, die die Abrechnung selbst erzeugt hat. Von Hand gebuchte Einbehalte oder Auszahlungen am selben Tag bleiben erhalten. (Ältere Abrechnungen ohne Kennzeichnung verhalten sich wie bisher.)
2. AfA: Auf der AfA-Seite des Objekts prüft das CRM die Instandsetzungs- und Modernisierungsbelege der ersten drei Jahre nach dem Kauf gegen die 15-%-Grenze für anschaffungsnahe Herstellungskosten (§ 6 Abs. 1 Nr. 1a EStG) und zeigt einen Hinweis. Rechnerische Prüfung ohne Gewähr, bitte mit dem Steuerberater abstimmen.
Neue Spalte deposit_entries.settlement (Migration). Selbsttest: korrektur:selbsttest

