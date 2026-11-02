# Änderungsverlauf

Neueste Pakete zuerst. Die Beschreibung steht auch im CRM beim jeweiligen Paket.

## Abmahnung und Kündigung
1. Menüpunkt Vermietung → „Schreiben an Mieter“ (vorher „Bescheinigungen“): Mietvertrag wählen, dann Wohnungsgeberbestätigung, Mietschuldenfreiheit, Abmahnung oder Kündigung erstellen.
2. Abmahnung: Grund (Zahlung, Ruhestörung, Hausordnung, Untervermietung, Tierhaltung, vertragswidriger Gebrauch, Sonstiges), genaue Schilderung, Aufforderung (Standardtext je Grund), freiwillige Frist, Androhung der Kündigung.
3. Kündigung: ordentlich (Wohnraum mit Grund nach § 573 BGB: Eigenbedarf, Pflichtverletzung, Verwertung, Sonstiges), fristlos wegen Zahlungsverzug (offene Monate aus dem Mieterkonto) oder fristlos aus wichtigem Grund; fristlos auf Wunsch mit hilfsweiser ordentlicher Kündigung.
4. Das Ende wird berechnet: Wohnraum 3/6/9 Monate je nach Mietdauer (§ 573c BGB), Gewerbe zum Quartal (§ 580a BGB), jeweils mit Zugang bis zum 3. Werktag (Samstag zählt mit, bundesweite Feiertage nicht). Die Seite zeigt, bis wann das Schreiben beim Mieter sein muss.
5. Prüfung des Zahlungsverzugs nach § 543 Abs. 2 Nr. 3 BGB; Hinweis auf Widerspruchsrecht (§ 574 BGB) bei Wohnraum; Widerspruch gegen stillschweigende Verlängerung (§ 545 BGB); Warnhinweise zu Schriftform und Zugang.
6. Jedes erstellte Schreiben steht im Protokoll.
Keine Migration. Selbsttest: bescheinigung:selbsttest

## Bescheinigungen im Menü
Neuer Menüpunkt Vermietung → Bescheinigungen: Mietvertrag suchen und auswählen (laufende, künftige und im letzten Jahr beendete), dann Wohnungsgeberbestätigung oder Mietschuldenfreiheitsbescheinigung erstellen. Bevorstehende Ein- und Auszüge sind markiert.
Keine Migration. Selbsttest: bescheinigung:selbsttest

## Neues Logo „Haus mit B“
1. Seitenleiste im CRM, Anmeldeseite und Webseite zeigen das Logo (Dach in der Akzentfarbe, Haus mit „B“), solange unter Webseite gestalten kein eigenes Logo hochgeladen ist. Ein hochgeladenes Logo hat weiterhin Vorrang.
2. App-Symbol (Startbildschirm) und Browser-Symbol zeigen jetzt das Logo auf orangem Grund.
Keine Migration. Selbsttest: design:selbsttest, pwa:selbsttest

## Bescheinigungen: Wohnungsgeberbestätigung und Mietschuldenfreiheit
1. Im Mietvertrag gibt es den Knopf „Bescheinigungen“. Dort lassen sich zwei Schreiben zum Drucken oder als PDF erstellen.
2. Wohnungsgeberbestätigung nach § 19 Bundesmeldegesetz: Einzug oder Auszug mit Datum, Anschrift und Lage der Wohnung, alle ein- bzw. ausziehenden Personen (eine je Zeile, Vorschlag: der Mieter), Hinweis zur Meldepflicht und zum Bußgeld.
3. Mietschuldenfreiheitsbescheinigung zu einem Stichtag: Vermieter, Mieter, Mietobjekt, Mietbeginn, monatliche Miete; auf Wunsch „stets pünktlich gezahlt“ und ein Zweck. Das Mieterkonto wird geprüft: Ist bis zum Stichtag etwas offen, wird keine Bescheinigung erstellt.
4. Jede erstellte Bescheinigung steht im Protokoll.
Keine Migration. Selbsttest: bescheinigung:selbsttest

## Menü-Überschriften deutlicher, Lieferanten unter Stammdaten
1. Gruppen-Überschriften im Seitenmenü (Finanzen, Vermietung, Nebenkosten, Instandhaltung und Dienstleister) sind jetzt hell und fett, mit Trennlinie darüber und Farbstrich links; die Punkte darunter sind eingerückt.
2. „Lieferanten“ steht nicht mehr im Seitenmenü, sondern als Kachel unter Einstellungen → Stammdaten.
Keine Migration. Selbsttest: design:selbsttest

## DATEV-Export unter Einstellungen
Der DATEV-Export steht nicht mehr im Seitenmenü, sondern als Kachel unter Einstellungen → Finanzen und Steuer (nur Administrator).
Keine Migration. Selbsttest: design:selbsttest

## Mietverträge, Finanzierung, Anlage V, Jahresbericht unter Einstellungen
1. Diese vier Punkte stehen nicht mehr im Seitenmenü, sondern als Kacheln unter Profilmenü → Einstellungen: Mietverträge bei „Stammdaten“, Finanzierung, Anlage V und Jahresbericht in der neuen Gruppe „Finanzen und Steuer“.
2. Seitenmenü jetzt: Mein Tag, E-Mails, Finanzen (Rechnungen schreiben, Umsätze, Mieteingang, Mahnwesen, Belege, E-Rechnung, DATEV-Export), Vermietung (Interessenten, Mieterhöhungen, Kautionen), Nebenkosten, Instandhaltung und Dienstleister.
Keine Migration. Selbsttest: design:selbsttest

## E-Mails löschen, Menü-Reihenfolge
1. E-Mails lassen sich löschen: Knopf „Löschen“ neben „Archivieren“ (mit Rückfrage). Die E-Mail und ihre Anhänge verschwinden aus dem CRM, der Vorgang steht im Protokoll. Im Postfach bei IONOS bleibt sie liegen und wird nicht noch einmal abgerufen.
2. Seitenmenü: ganz oben Mein Tag, darunter E-Mails, dann Finanzen.
Keine Migration. Selbsttest: posteingang:selbsttest, design:selbsttest

## Kontoauszug einlesen: 500-Fehler behoben, „Offene Posten“ aus dem Menü
1. Behoben: Die Seite Umsätze → Kontoauszug einlesen brach mit „500 Server Error“ ab, sobald schon Dateien eingelesen waren. Ursache war eine Blade-Schreibweise („@php(…)“ vor einem „@php … @endphp“-Block), die Blade falsch übersetzt.
2. design:selbsttest übersetzt jetzt alle Vorlagen und schlägt bei diesem Fehler an.
3. „Offene Posten“ ist kein eigener Menüpunkt mehr; die Seite erreichst du über Mieteingang (Knopf „Offene Posten“).
Keine Migration. Selbsttest: design:selbsttest

## Finanzen ganz oben, Wiederkehrende Ausgaben unter Umsätze
1. Die Gruppe „Finanzen“ steht ganz oben im Seitenmenü. Reihenfolge: Rechnungen schreiben, Umsätze, Mieteingang, Offene Posten, Mahnwesen, Belege, E-Rechnung, Finanzierung, Anlage V, Jahresbericht, DATEV-Export.
2. „Wiederkehrende Ausgaben“ ist jetzt ein Reiter auf der Seite Umsätze (Eingänge, Ausgaben, Wiederkehrende Ausgaben, Kontoauszug einlesen) und kein eigener Menüpunkt mehr.
3. Kontoauszug einlesen: Die Dateiauswahl lässt jede Datei zu (auf Handy und iPad war die CSV-Datei teils ausgegraut). Nach dem Einlesen werden alle Monate gezeigt, damit die neuen Umsätze nicht von einer gespeicherten Monatsauswahl verdeckt werden.
Keine Migration. Selbsttest: design:selbsttest, mieten:selbsttest

## Kontoauszüge heißen jetzt „Umsätze“
Menüpunkt, Seitentitel und Hinweise sagen „Umsätze“ (Reiter Eingänge, Ausgaben, Einlesen). Die eingelesene Datei heißt weiter Kontoauszug.
Keine Migration.

## Finanzen im Seitenmenü gesammelt
1. Neue Gruppe „Finanzen“: Mieteingang, Offene Posten, Mahnwesen, Kontoauszüge, Belege, Wiederkehrende Ausgaben, Rechnungen schreiben, E-Rechnung, Finanzierung, Anlage V, Jahresbericht, DATEV-Export.
2. Übrige Gruppen: „Nebenkosten“ (Nebenkosten, Zähler und CO2) und „Instandhaltung und Dienstleister“ (Schäden und Aufträge, Wartungsplan, Laufende Verträge, Lieferanten). Die Gruppen „Mieteingang und Mahnung“, „Kosten“ und „Abrechnung und Steuer“ entfallen.
Keine Migration. Selbsttest: design:selbsttest

## Seitenmenü einklappbar
1. Am PC und Tablet blendet der Knopf „☰“ oben links in der Kopfleiste das Seitenmenü aus und wieder ein; im Menü steht außerdem „« Menü einklappen“. Die Seite nutzt dann die volle Breite. Der Zustand bleibt im Browser gespeichert.
2. Am Handy bleibt alles wie bisher („☰ Menü“ öffnet das Menü von links).
Keine Migration. Selbsttest: design:selbsttest

## Mieten erfasst ab 10.2026
1. Soll-Mieten entstehen erst ab dem Monat „Mieten erfasst ab“ (Standard Oktober 2026). Ältere Monate gelten als erledigt: kein Rückstand, keine Mahnung, kein Kündigungshinweis. Vorhandene ältere Soll-Posten werden bei der nächsten Berechnung entfernt.
2. Der Monat lässt sich unter Mieteingang → Offene Posten (unten, nur Administrator) ändern; danach werden alle Mietkonten sofort neu berechnet.
3. Behoben: Kontoauszüge → Eingänge brach mit „500 Server Error“ ab (Fenster „Aufteilen …“).
Keine Migration. Selbsttest: mieten:selbsttest

## Miete korrigieren
1. Im Mietvertrag unter „Mietstand und Verlauf“ hat jede Zeile „✎ Korrigieren“, auch die Miete zum Vertragsbeginn. Dort lassen sich falsch erfasste Beträge (Kaltmiete, Vorauszahlungen, Stellplatz usw.) und die Notiz ändern; der Monat bleibt. Alle betroffenen Monate werden neu berechnet, die Änderung steht im Protokoll.
2. Die Seite zeigt die Umsatzsteuer des Vertrags mit Link zu „Vertrag bearbeiten“, wo sie umgestellt wird (z. B. auf „keine“).
3. Schon geltende Beträge darf nur der Administrator korrigieren (wie beim Entfernen).
Keine Migration. Selbsttest: mieten:selbsttest

## Gutschrift aufteilen wie bei Objego
1. Unter Kontoauszüge → Eingänge zuordnen hat jede offene Gutschrift den Knopf „Aufteilen …“. Es öffnet sich ein Fenster mit beliebig vielen Zeilen: Einnahmeart, Mietvertrag, Monat, Betrag, Notiz. Darunter steht die Gesamtsumme; sie darf den offenen Betrag nicht übersteigen (ein Rest bleibt offen).
2. Einnahmearten: Miete (auf den gewählten Monat oder automatisch auf den ältesten offenen), Nebenkosten-Nachzahlung (geht auf offene Nachzahlungen), Mahngebühr / Zinsen (geht auf diese Posten), Kaution (wird bei der Kaution als Einzahlung gebucht), Sonstiges (keine Miete, mit Notiz).
3. Zuordnungen zeigen die Einnahmeart, z. B. „Miete Oktober 2026: 900,00 €“, auch im Mieterkonto. Unter „Bearbeiten“ lassen sich Kaution- und Sonstiges-Teile einzeln entfernen; „Zurücksetzen“ nimmt auch diese zurück (die Kautionsbuchung wird mit gelöscht, solange die Kaution nicht abgerechnet ist).
4. Der schnelle Knopf „Zuordnen“ bleibt wie bisher.
Migration: rent_payments.income_kind und for_period, neue Tabelle bank_transaction_items. Selbsttest: mieten:selbsttest

## Kontoauszüge: eine Seite mit Reitern und Monatsauswahl
1. Im Seitenmenü gibt es nur noch „Kontoauszüge“ (Zahl = offene Eingänge + Ausgaben). „Ausgaben abgleichen“ ist dort als eigener Punkt entfallen.
2. Die Seite hat oben drei Reiter: „Eingänge zuordnen“ (bisher Kontoumsätze/Umsätze zuordnen), „Ausgaben zuordnen“ (bisher Ausgaben abgleichen) und „Kontoauszug einlesen“.
3. Neu: Monat wählen (Auswahlliste mit Pfeilen ‹ ›), auch zusammen mit dem Konto. Der Monat bleibt beim Zuordnen und beim Wechsel zwischen Eingängen und Ausgaben erhalten. „Alle Monate“ zeigt wieder alles.
Keine Migration. Selbsttest: mieten:selbsttest, design:selbsttest

## Suche neben dem Profil
1. Das Suchfeld in der Kopfleiste steht jetzt rechts direkt neben dem Profilzeichen.
2. Der doppelte Punkt „Suche“ im Seitenmenü ist entfernt.
Keine Migration.

## Ein Menü statt „Alltag“ und „Verwaltung“
1. Der Umschalter „Alltag | Verwaltung“ oben im Seitenmenü ist weg. Das Menü zeigt immer alle Punkte (wie bisher „Verwaltung“).
2. Die Kachel „Menü-Ansicht“ unter Profilmenü → Einstellungen und die Seite /menue-ansicht sind entfernt. Ein altes Cookie `nav_mode` wird nicht mehr beachtet.
Keine Migration. Selbsttest: tag:selbsttest

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

