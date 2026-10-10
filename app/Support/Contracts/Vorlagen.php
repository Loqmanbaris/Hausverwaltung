<?php

namespace App\Support\Contracts;

/**
 * Mustertexte für Mietverträge (Paket 12). Eigene Formulierungen, keine Übernahme fremder Formulare.
 *
 * Schreibweise:
 *  - {{name}}                 Platzhalter (siehe ContractData::PLACEHOLDERS)
 *  - [[wenn name]]…[[/wenn]]  nur wenn der Wert vorhanden bzw. „ja“ ist
 *  - [[wenn nicht name]]…[[/wenn]]
 *  - (#) am Zeilenanfang      Absatz mit laufender Nummer (1), (2) …
 *  - Leerzeile                neuer Absatz
 * Ein Abschnitt, der nach dem Einsetzen leer ist, entfällt samt Überschrift.
 *
 * Hinweis: Muster ohne Gewähr. Vor dem ersten Einsatz rechtlich prüfen lassen (z. B. Haus & Grund oder Fachanwalt).
 */
class Vorlagen
{
    public const KINDS = [
        'wohnen' => 'Wohnraum',
        'gewerbe' => 'Gewerbe',
        'montage' => 'Montage-Wohnung',
    ];

    public const TITLES = [
        'wohnen' => 'Mietvertrag über Wohnraum',
        'gewerbe' => 'Mietvertrag über Gewerberäume',
        'montage' => 'Vertrag über die Vermietung einer möblierten Monteurunterkunft',
    ];

    /** @return array<int, array{key: string, title: string, text: string, enabled: bool}> */
    public static function defaults(string $kind): array
    {
        $list = match ($kind) {
            'gewerbe' => self::gewerbe(),
            'montage' => self::montage(),
            default => self::wohnen(),
        };

        return array_map(fn ($s) => $s + ['enabled' => true], $list);
    }

    private static function wohnen(): array
    {
        return [
            ['key' => 'mietsache', 'title' => 'Mietsache', 'text' => <<<'T'
(#) Der Vermieter vermietet dem Mieter zu Wohnzwecken die Wohnung „{{einheit}}“[[wenn etage]] ({{etage}})[[/wenn]] im Haus {{objekt_anschrift}}.

(#) Die Wohnung besteht aus {{zimmer}} Zimmern[[wenn raeume]] ({{raeume}})[[/wenn]]. Die Wohnfläche beträgt ca. {{flaeche}} m².

[[wenn nebenraeume]](#) Mitvermietet sind außerdem: {{nebenraeume}}.[[/wenn]]

[[wenn ausstattung]](#) Zur Wohnung gehören folgende vom Vermieter gestellte Einrichtungsgegenstände: {{ausstattung}}.[[/wenn]]

[[wenn stellplatz]](#) Mitvermietet ist: {{stellplatz}}.[[/wenn]]

(#) Gemeinschaftliche Einrichtungen des Hauses (z. B. Hof, Wasch- und Trockenraum) darf der Mieter nach Maßgabe der Hausordnung mitbenutzen.
T],
            ['key' => 'mietzeit', 'title' => 'Mietzeit und Kündigung', 'text' => <<<'T'
[[wenn nicht befristet]](#) Das Mietverhältnis beginnt am {{beginn}} und läuft auf unbestimmte Zeit.[[/wenn]]
[[wenn befristet]](#) Das Mietverhältnis beginnt am {{beginn}} und endet am {{ende}}, ohne dass es einer Kündigung bedarf. Grund der Befristung (§ 575 BGB): {{befristungsgrund}}.[[/wenn]]

[[wenn kuendigungsverzicht]](#) Beide Vertragsparteien verzichten wechselseitig für die Dauer von {{kuendigungsverzicht}} Jahr(en) ab Beginn des Mietverhältnisses auf ihr Recht zur ordentlichen Kündigung. Eine ordentliche Kündigung ist erstmals zum Ablauf dieses Zeitraums zulässig. Das Recht zur außerordentlichen Kündigung bleibt unberührt.[[/wenn]]

(#) Für die Kündigung gelten die gesetzlichen Vorschriften. Sie bedarf der Schriftform (§ 568 BGB).

(#) Setzt der Mieter den Gebrauch der Mietsache nach Ablauf der Mietzeit fort, verlängert sich das Mietverhältnis nicht stillschweigend. § 545 BGB findet keine Anwendung.
T],
            ['key' => 'miete', 'title' => 'Miete', 'text' => <<<'T'
(#) Die Miete beträgt monatlich:
{{miete_aufstellung}}

(#) Die Miete ist monatlich im Voraus, spätestens bis zum {{faellig_text}} eines jeden Monats zu zahlen, und zwar auf das Konto {{vermieter_kontoinhaber}}, IBAN {{vermieter_iban}}[[wenn vermieter_bank]] ({{vermieter_bank}})[[/wenn]].

[[wenn staffel]](#) Es wird eine Staffelmiete nach § 557a BGB vereinbart. Die Grundmiete erhöht sich wie folgt:
{{staffel_liste}}
Während der Laufzeit der Staffelmiete sind Mieterhöhungen nach den §§ 558 bis 559b BGB ausgeschlossen.[[/wenn]]

[[wenn index]](#) Es wird eine Indexmiete nach § 557b BGB vereinbart. Die Grundmiete ist an den vom Statistischen Bundesamt ermittelten Verbraucherpreisindex für Deutschland gebunden. Ändert sich der Index, kann jede Vertragspartei durch Erklärung in Textform eine entsprechende Anpassung der Grundmiete verlangen; die Erklärung muss die eingetretene Änderung des Index sowie die geänderte Miete oder die Erhöhung in einem Geldbetrag angeben. Die geänderte Miete ist mit Beginn des übernächsten Monats nach Zugang der Erklärung zu zahlen. Die Grundmiete muss jeweils mindestens ein Jahr unverändert bleiben. Ausgangswert ist der Index für {{index_basis_monat}} mit {{index_basis_wert}} Punkten.[[/wenn]]
T],
            ['key' => 'betriebskosten', 'title' => 'Betriebskosten', 'text' => <<<'T'
(#) Neben der Grundmiete trägt der Mieter die Betriebskosten im Sinne der Betriebskostenverordnung (BetrKV) in ihrer jeweils geltenden Fassung. Das sind insbesondere: Grundsteuer; Wasserversorgung und Entwässerung; Heizung und Warmwasser; Aufzug; Straßenreinigung und Müllbeseitigung; Gebäudereinigung und Ungezieferbekämpfung; Gartenpflege; Beleuchtung; Schornsteinreinigung; Sach- und Haftpflichtversicherung; Hauswart; Gemeinschaftsantenne oder Breitbandanschluss; Einrichtungen für die Wäschepflege; als sonstige Betriebskosten die Wartung der Rauchwarnmelder, die Dachrinnenreinigung sowie die Prüfung der Elektroanlagen.

(#) Auf die Betriebskosten leistet der Mieter monatliche Vorauszahlungen in der oben genannten Höhe. Über die Vorauszahlungen wird jährlich abgerechnet. Nach einer Abrechnung kann jede Vertragspartei eine Anpassung der Vorauszahlungen auf eine angemessene Höhe verlangen.

(#) Die Betriebskosten werden nach dem Anteil der Wohnfläche umgelegt, soweit nicht nach Verbrauch, nach Personen oder nach Wohneinheiten abgerechnet wird oder gesetzlich etwas anderes vorgeschrieben ist. Die Kosten für Heizung und Warmwasser werden nach der Heizkostenverordnung verteilt.

(#) Entstehen nach Vertragsschluss neue Betriebskosten im Sinne der BetrKV, kann der Vermieter diese im Rahmen der Wirtschaftlichkeit anteilig umlegen, wenn er dies dem Mieter vorher in Textform mitteilt.
T],
            ['key' => 'kaution', 'title' => 'Mietsicherheit', 'text' => <<<'T'
[[wenn kaution]](#) Der Mieter leistet eine Mietsicherheit (Kaution) in Höhe von {{kaution}}.

(#) Der Mieter ist berechtigt, die Kaution in drei gleichen monatlichen Teilzahlungen zu erbringen. Die erste Teilzahlung ist zu Beginn des Mietverhältnisses fällig, die weiteren mit den unmittelbar folgenden Mietzahlungen.

(#) Der Vermieter legt die Kaution getrennt von seinem Vermögen bei einem Kreditinstitut zu dem für Spareinlagen mit dreimonatiger Kündigungsfrist üblichen Zinssatz an. Die Zinsen stehen dem Mieter zu und erhöhen die Sicherheit.[[/wenn]]
T],
            ['key' => 'uebergabe', 'title' => 'Übergabe und Schlüssel', 'text' => <<<'T'
(#) Die Wohnung wird in dem Zustand übergeben, der sich aus dem bei der Übergabe gemeinsam erstellten Protokoll ergibt.[[wenn renoviert]] Die Wohnung wird renoviert übergeben.[[/wenn]]

(#) Der Mieter erhält folgende Schlüssel: {{schluessel}}. Weitere Schlüssel darf der Mieter nur mit Zustimmung des Vermieters anfertigen lassen. Der Verlust eines Schlüssels ist dem Vermieter unverzüglich mitzuteilen.
T],
            ['key' => 'schoenheit', 'title' => 'Schönheitsreparaturen', 'text' => <<<'T'
[[wenn renoviert]](#) Der Mieter übernimmt auf seine Kosten die Schönheitsreparaturen, soweit sie während der Mietzeit durch seinen Gebrauch erforderlich werden. Dazu gehören das Tapezieren, Anstreichen oder Kalken der Wände und Decken, das Streichen der Heizkörper einschließlich der Heizrohre, der Innentüren sowie der Fenster und Außentüren von innen. Feste Fristen bestehen nicht; maßgeblich ist allein der tatsächliche Zustand der Räume.[[/wenn]]
[[wenn nicht renoviert]](#) Die Wohnung wird nicht renoviert übergeben. Für Schönheitsreparaturen gelten die gesetzlichen Vorschriften.[[/wenn]]
T],
            ['key' => 'kleinreparaturen', 'title' => 'Kleinreparaturen', 'text' => <<<'T'
(#) Der Mieter trägt die Kosten für kleine Instandhaltungen an Teilen der Wohnung, die seinem häufigen und unmittelbaren Zugriff ausgesetzt sind, wie Installationsgegenstände für Elektrizität, Wasser und Gas, Heiz- und Kocheinrichtungen, Fenster- und Türverschlüsse sowie Verschlussvorrichtungen von Fensterläden, soweit die Kosten im Einzelfall 120 € nicht übersteigen.

(#) Die Kosten nach Absatz 1 trägt der Mieter innerhalb eines Kalenderjahres höchstens bis zu 8 % der Jahresgrundmiete, jedoch nicht mehr als 360 €.
T],
            ['key' => 'gebrauch', 'title' => 'Gebrauch der Wohnung', 'text' => <<<'T'
(#) Der Mieter behandelt die Wohnung und die gemeinschaftlichen Einrichtungen pfleglich. Er sorgt für ausreichendes Heizen und Lüften.

(#) Eine Untervermietung oder sonstige Gebrauchsüberlassung an Dritte ist nur mit Erlaubnis des Vermieters zulässig. Die gesetzlichen Ansprüche des Mieters auf Erlaubnis bleiben unberührt.

(#) Kleintiere (z. B. Fische, Hamster, Vögel) darf der Mieter ohne Erlaubnis halten. Für andere Tiere, insbesondere Hunde und Katzen, ist die vorherige Zustimmung des Vermieters erforderlich; sie darf nur aus sachlichem Grund verweigert werden.

(#) Bauliche Veränderungen, insbesondere Um- und Einbauten, bedürfen der vorherigen Zustimmung des Vermieters.

(#) Mängel der Wohnung hat der Mieter dem Vermieter unverzüglich anzuzeigen.
T],
            ['key' => 'betreten', 'title' => 'Betreten der Wohnung', 'text' => <<<'T'
(#) Der Vermieter oder ein Beauftragter darf die Wohnung aus sachlichem Grund nach rechtzeitiger Ankündigung zu angemessener Tageszeit betreten, insbesondere zur Ablesung von Zählern, zur Prüfung des Zustands, zur Durchführung von Arbeiten oder zur Besichtigung durch Miet- oder Kaufinteressenten. Bei Gefahr ist ein Betreten jederzeit zulässig.
T],
            ['key' => 'rauchmelder', 'title' => 'Rauchwarnmelder', 'text' => <<<'T'
(#) Die Wohnung ist mit Rauchwarnmeldern ausgestattet. Der Vermieter sorgt für ihre Wartung. Der Mieter darf die Melder nicht entfernen oder abdecken und meldet Störungen unverzüglich.
T],
            ['key' => 'rueckgabe', 'title' => 'Rückgabe der Wohnung', 'text' => <<<'T'
(#) Bei Ende des Mietverhältnisses gibt der Mieter die Wohnung geräumt, besenrein und mit allen Schlüsseln zurück. Vom Mieter eingebrachte Einrichtungen sind zu entfernen und der ursprüngliche Zustand ist wiederherzustellen, soweit nichts anderes vereinbart ist.
T],
            ['key' => 'hausordnung', 'title' => 'Hausordnung', 'text' => <<<'T'
(#) Die folgende Hausordnung ist Bestandteil dieses Vertrags.

(#) Ruhezeiten sind werktags von 22 bis 6 Uhr sowie an Sonn- und Feiertagen ganztägig. Musik und Geräte sind stets auf Zimmerlautstärke zu halten.

(#) Treppenhaus, Flure und Zugänge sind freizuhalten. Fahrräder, Kinderwagen und andere Gegenstände dürfen dort nur abgestellt werden, wo es ausdrücklich erlaubt ist.

(#) Abfall ist getrennt in den dafür vorgesehenen Behältern zu entsorgen. Sperrmüll darf nicht im Haus oder auf dem Grundstück abgestellt werden.

(#) Das Grillen mit Holzkohle auf Balkonen und Terrassen ist nicht gestattet.

(#) Haus- und Hoftüren sind nachts geschlossen zu halten.
T],
            ['key' => 'datenschutz', 'title' => 'Datenschutz', 'text' => <<<'T'
(#) Der Vermieter verarbeitet die personenbezogenen Daten des Mieters, soweit dies zur Durchführung des Mietverhältnisses erforderlich ist (Art. 6 Abs. 1 lit. b DSGVO), sowie zur Erfüllung gesetzlicher Pflichten. Weitere Informationen enthalten die Datenschutzhinweise des Vermieters.
T],
            ['key' => 'schluss', 'title' => 'Sonstige Vereinbarungen', 'text' => <<<'T'
[[wenn sondervereinbarungen]](#) Zusätzlich wird vereinbart:
{{sondervereinbarungen}}[[/wenn]]

[[wenn energieausweis]](#) Der Energieausweis für das Gebäude wurde dem Mieter vor Vertragsschluss vorgelegt und in Kopie übergeben.[[/wenn]]

(#) Änderungen und Ergänzungen dieses Vertrags sollen zu Beweiszwecken in Textform festgehalten werden.

(#) Sollte eine Bestimmung dieses Vertrags unwirksam sein, bleibt der Vertrag im Übrigen wirksam. An die Stelle der unwirksamen Bestimmung tritt die gesetzliche Regelung.

(#) Jede Vertragspartei erhält eine Ausfertigung dieses Vertrags.
T],
        ];
    }

    private static function gewerbe(): array
    {
        return [
            ['key' => 'mietobjekt', 'title' => 'Mietobjekt und Nutzungszweck', 'text' => <<<'T'
(#) Der Vermieter vermietet dem Mieter die Gewerbeeinheit „{{einheit}}“[[wenn etage]] ({{etage}})[[/wenn]] im Objekt {{objekt_anschrift}} mit einer Fläche von ca. {{flaeche}} m²[[wenn raeume]], bestehend aus {{raeume}}[[/wenn]].

[[wenn nebenraeume]](#) Mitvermietet sind außerdem: {{nebenraeume}}.[[/wenn]]

[[wenn stellplatz]](#) Mitvermietet ist: {{stellplatz}}.[[/wenn]]

(#) Die Räume werden ausschließlich zur Nutzung als {{nutzungszweck}} vermietet. Eine Änderung der Nutzung bedarf der vorherigen Zustimmung des Vermieters.

(#) Behördliche Genehmigungen, die für den Betrieb des Mieters erforderlich sind, beschafft der Mieter auf eigene Kosten. Der Vermieter steht dafür ein, dass die Räume baurechtlich für die vereinbarte Nutzungsart grundsätzlich zulässig sind.

(#) Ein Konkurrenzschutz wird nicht gewährt.
T],
            ['key' => 'mietzeit', 'title' => 'Mietzeit und Kündigung', 'text' => <<<'T'
[[wenn befristet]](#) Das Mietverhältnis beginnt am {{beginn}} und endet am {{ende}}.[[/wenn]]
[[wenn nicht befristet]](#) Das Mietverhältnis beginnt am {{beginn}} und läuft auf unbestimmte Zeit. Es kann von jeder Vertragspartei nach den gesetzlichen Vorschriften gekündigt werden (§ 580a Abs. 2 BGB).[[/wenn]]

[[wenn verlaengerungsoption]](#) Verlängerungsoption: {{verlaengerungsoption}}[[/wenn]]

(#) Die Kündigung bedarf der Schriftform.

(#) Setzt der Mieter den Gebrauch der Mietsache nach Ablauf der Mietzeit fort, verlängert sich das Mietverhältnis nicht stillschweigend. § 545 BGB findet keine Anwendung.
T],
            ['key' => 'miete', 'title' => 'Miete und Nebenkosten', 'text' => <<<'T'
(#) Die Miete beträgt monatlich:
{{miete_aufstellung}}

[[wenn ust]](#) Der Vermieter hat für dieses Mietverhältnis zur Umsatzsteuer optiert. Alle Beträge verstehen sich zuzüglich der gesetzlichen Umsatzsteuer, derzeit {{ust_satz}} %. Der Mieter versichert, die Räume ausschließlich für Umsätze zu verwenden, die den Vorsteuerabzug nicht ausschließen. Er teilt eine Änderung unverzüglich mit und ersetzt dem Vermieter den Schaden, der durch den Verlust der Option entsteht.[[/wenn]]

(#) Die Miete ist monatlich im Voraus, spätestens bis zum {{faellig_text}} eines jeden Monats auf das Konto {{vermieter_kontoinhaber}}, IBAN {{vermieter_iban}}[[wenn vermieter_bank]] ({{vermieter_bank}})[[/wenn]], zu zahlen. Für die Rechtzeitigkeit kommt es auf den Eingang des Geldes an.

(#) Der Mieter trägt die Betriebskosten im Sinne der Betriebskostenverordnung in ihrer jeweils geltenden Fassung. Auf die Betriebskosten leistet er die oben genannten Vorauszahlungen, über die jährlich abgerechnet wird. Die Kosten werden nach dem Anteil der Fläche verteilt, soweit nicht nach Verbrauch abgerechnet wird.

[[wenn staffel]](#) Die Grundmiete erhöht sich wie folgt:
{{staffel_liste}}[[/wenn]]

[[wenn index]](#) Wertsicherung: Die Grundmiete ist an den vom Statistischen Bundesamt ermittelten Verbraucherpreisindex für Deutschland gebunden. Ausgangswert ist der Index für {{index_basis_monat}} mit {{index_basis_wert}} Punkten. Ändert sich der Index gegenüber dem Ausgangswert oder der letzten Anpassung um mehr als 5 %, ändert sich die Grundmiete im selben prozentualen Verhältnis ab dem Monat, der auf die Mitteilung in Textform folgt.[[/wenn]]
T],
            ['key' => 'kaution', 'title' => 'Mietsicherheit', 'text' => <<<'T'
[[wenn kaution]](#) Der Mieter leistet eine Mietsicherheit in Höhe von {{kaution}} bis zum Beginn des Mietverhältnisses.[[wenn buergschaft]] Die Sicherheit kann auch durch eine unbefristete, selbstschuldnerische Bürgschaft eines deutschen Kreditinstituts erbracht werden.[[/wenn]][[/wenn]]
T],
            ['key' => 'zustand', 'title' => 'Zustand, Instandhaltung und Reparaturen', 'text' => <<<'T'
(#) Die Räume werden in dem Zustand übergeben, der sich aus dem gemeinsamen Übergabeprotokoll ergibt.

(#) Der Mieter übernimmt die Schönheitsreparaturen innerhalb der Mieträume, soweit sie durch seinen Gebrauch erforderlich werden. Maßgeblich ist der tatsächliche Zustand.

(#) Der Mieter trägt die Kosten kleiner Instandhaltungen an Teilen innerhalb der Mieträume, die seinem häufigen Zugriff ausgesetzt sind, bis zu 150 € im Einzelfall und höchstens 8 % der Jahresgrundmiete im Kalenderjahr. Instandhaltung und Instandsetzung von Dach und Fach obliegen dem Vermieter.

(#) Bauliche Veränderungen und das Anbringen von Schildern oder Werbeanlagen an der Außenfassade bedürfen der vorherigen Zustimmung des Vermieters.
T],
            ['key' => 'versicherung', 'title' => 'Versicherung und Haftung', 'text' => <<<'T'
(#) Der Mieter schließt für seinen Betrieb eine Betriebshaftpflichtversicherung ab und versichert seine Einrichtung und Waren auf eigene Kosten.

(#) Der Mieter haftet für Schäden, die durch ihn, seine Beschäftigten, Kunden oder Lieferanten schuldhaft verursacht werden.
T],
            ['key' => 'untervermietung', 'title' => 'Untervermietung', 'text' => <<<'T'
(#) Eine Untervermietung oder sonstige Überlassung an Dritte, auch teilweise, ist nur mit vorheriger Zustimmung des Vermieters zulässig.
T],
            ['key' => 'betreten', 'title' => 'Betreten und Rückgabe', 'text' => <<<'T'
(#) Der Vermieter darf die Räume nach Ankündigung während der Geschäftszeiten aus sachlichem Grund betreten, bei Gefahr jederzeit.

(#) Bei Ende des Mietverhältnisses gibt der Mieter die Räume geräumt, besenrein und mit allen Schlüsseln zurück. Einbauten des Mieters sind auf Verlangen des Vermieters zu entfernen.

(#) Der Mieter erhält folgende Schlüssel: {{schluessel}}.
T],
            ['key' => 'schluss', 'title' => 'Sonstige Vereinbarungen', 'text' => <<<'T'
[[wenn sondervereinbarungen]](#) Zusätzlich wird vereinbart:
{{sondervereinbarungen}}[[/wenn]]

[[wenn energieausweis]](#) Der Energieausweis für das Gebäude wurde dem Mieter vor Vertragsschluss vorgelegt.[[/wenn]]

(#) Änderungen und Ergänzungen dieses Vertrags sollen zu Beweiszwecken in Textform festgehalten werden.

(#) Sollte eine Bestimmung dieses Vertrags unwirksam sein, bleibt der Vertrag im Übrigen wirksam. An die Stelle der unwirksamen Bestimmung tritt die gesetzliche Regelung.

(#) Jede Vertragspartei erhält eine Ausfertigung dieses Vertrags.
T],
        ];
    }

    private static function montage(): array
    {
        return [
            ['key' => 'gegenstand', 'title' => 'Gegenstand des Vertrags', 'text' => <<<'T'
(#) Der Vermieter überlässt dem Mieter die möblierte Wohnung „{{einheit}}“[[wenn etage]] ({{etage}})[[/wenn]] im Haus {{objekt_anschrift}}[[wenn flaeche]] mit ca. {{flaeche}} m²[[/wenn]] zur vorübergehenden Unterbringung seiner Mitarbeiter.

(#) Die Überlassung erfolgt nur zum vorübergehenden Gebrauch (§ 549 Abs. 2 Nr. 1 BGB). Die Wohnung darf von höchstens {{max_personen}} Personen gleichzeitig bewohnt werden.

[[wenn ausstattung]](#) Ausstattung: {{ausstattung}}.[[/wenn]]

[[wenn stellplatz]](#) Mitvermietet ist: {{stellplatz}}.[[/wenn]]
T],
            ['key' => 'zeitraum', 'title' => 'Zeitraum', 'text' => <<<'T'
[[wenn befristet]](#) Die Überlassung beginnt am {{beginn}} (Anreise) und endet am {{ende}} (Abreise).[[/wenn]]
[[wenn nicht befristet]](#) Die Überlassung beginnt am {{beginn}} (Anreise) und läuft auf unbestimmte Zeit. Sie kann von jeder Vertragspartei mit einer Frist von {{kuendigungsfrist}} in Textform beendet werden.[[/wenn]]

(#) Die Anreise ist ab 14 Uhr möglich, die Abreise hat bis 11 Uhr zu erfolgen. Abweichende Zeiten bitte vorher absprechen.

(#) Bei vorzeitiger Abreise bleibt der Preis bis zum vereinbarten Ende bzw. bis zum Ablauf der Frist nach Absatz 1 geschuldet, soweit die Wohnung nicht anderweitig vermietet werden kann.
T],
            ['key' => 'bewohner', 'title' => 'Bewohner', 'text' => <<<'T'
[[wenn personen_namen]](#) Die Wohnung wird von folgenden Personen genutzt: {{personen_namen}}.[[/wenn]]

(#) Der Mieter teilt dem Vermieter vor der Anreise die Namen der Bewohner und jeden Wechsel mit. Weitere Personen dürfen nicht aufgenommen werden.

(#) Der Mieter steht dafür ein, dass die Bewohner diesen Vertrag und die Hausordnung einhalten. Er haftet für Schäden, die die Bewohner verursachen.
T],
            ['key' => 'preis', 'title' => 'Preis und Zahlung', 'text' => <<<'T'
(#) Der Preis beträgt {{preis_text}}.[[wenn ust]] Alle Beträge verstehen sich zuzüglich Umsatzsteuer, derzeit {{ust_satz}} %.[[/wenn]]

[[wenn endreinigung]](#) Für die Endreinigung wird einmalig {{endreinigung}} berechnet.[[/wenn]]

(#) Im Preis enthalten sind Strom, Wasser, Heizung, Internet (WLAN) sowie die Nutzung der Einrichtung und der Bettwäsche.

(#) Der Preis ist im Voraus zu zahlen. Rechnungen sind innerhalb von {{zahlungsziel}} Tagen ohne Abzug auf das Konto {{vermieter_kontoinhaber}}, IBAN {{vermieter_iban}}[[wenn vermieter_bank]] ({{vermieter_bank}})[[/wenn]], zu begleichen.
T],
            ['key' => 'kaution', 'title' => 'Kaution', 'text' => <<<'T'
[[wenn kaution]](#) Der Mieter leistet vor der Anreise eine Kaution von {{kaution}}. Sie wird nach der Abreise und Abnahme der Wohnung, abzüglich etwaiger Forderungen, zurückgezahlt.[[/wenn]]
T],
            ['key' => 'pflichten', 'title' => 'Pflichten der Bewohner', 'text' => <<<'T'
(#) In der Wohnung darf nicht geraucht werden. Haustiere sind nicht erlaubt.

(#) Die Ruhezeiten von 22 bis 6 Uhr sowie an Sonn- und Feiertagen sind einzuhalten.

(#) Abfall ist getrennt in den dafür vorgesehenen Behältern zu entsorgen. Die Wohnung ist sauber zu halten und regelmäßig zu lüften.

(#) Arbeitsmaterial, Werkzeug und Maschinen dürfen nicht in der Wohnung, im Treppenhaus oder auf den Gemeinschaftsflächen gelagert werden.

(#) Schäden sind dem Vermieter unverzüglich zu melden.
T],
            ['key' => 'schluessel', 'title' => 'Schlüssel und Rückgabe', 'text' => <<<'T'
(#) Der Mieter erhält folgende Schlüssel: {{schluessel}}. Bei Verlust trägt der Mieter die Kosten für Ersatz und, soweit aus Sicherheitsgründen erforderlich, für den Austausch der Schließanlage.

(#) Bei Abreise ist die Wohnung aufgeräumt, mit geleertem Kühlschrank und mit allen Schlüsseln zurückzugeben.
T],
            ['key' => 'schluss', 'title' => 'Sonstige Vereinbarungen', 'text' => <<<'T'
[[wenn sondervereinbarungen]](#) Zusätzlich wird vereinbart:
{{sondervereinbarungen}}[[/wenn]]

(#) Sollte eine Bestimmung dieses Vertrags unwirksam sein, bleibt der Vertrag im Übrigen wirksam.

(#) Jede Vertragspartei erhält eine Ausfertigung dieses Vertrags.
T],
        ];
    }
}
