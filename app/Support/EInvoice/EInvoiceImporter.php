<?php

namespace App\Support\EInvoice;

use App\Models\Receipt;
use App\Models\User;
use App\Support\Documents\UploadInspector;
use App\Support\Documents\UploadRejected;
use App\Support\Kostenarten;
use App\Support\Receipts\ReceiptService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Nimmt eine E-Rechnung (XRechnung als XML oder ZUGFeRD/Factur-X als PDF) entgegen und legt daraus einen Beleg an.
 * Die Originaldatei wird unverändert und verschlüsselt aufbewahrt, die Angaben werden daraus übernommen.
 */
class EInvoiceImporter
{
    /**
     * Prüft die Datei und liest die E-Rechnung aus. Es wird noch nichts gespeichert.
     *
     * @return array{file: array, parsed: array, via: string}
     *
     * @throws EInvoiceException
     */
    public static function prepare(string $path, string $clientName): array
    {
        $name = self::cleanName($clientName);

        if (! is_file($path) || ! is_readable($path)) {
            throw new EInvoiceException("„{$name}“ konnte nicht gelesen werden.");
        }
        $size = (int) filesize($path);
        if ($size === 0) {
            throw new EInvoiceException("„{$name}“ ist leer.");
        }
        if ($size > UploadInspector::MAX_BYTES) {
            throw new EInvoiceException("„{$name}“ ist größer als 10 MB.");
        }

        $content = (string) file_get_contents($path);
        $head = ltrim(substr($content, 0, 32), "\xEF\xBB\xBF \t\r\n");

        if (str_starts_with($head, '%PDF-')) {
            try {
                $file = UploadInspector::inspect($path, $clientName);
            } catch (UploadRejected $e) {
                throw new EInvoiceException($e->getMessage());
            }
            $xml = PdfXmlExtractor::extract($file['content']);
            if ($xml === null) {
                throw new EInvoiceException("In „{$name}“ steckt keine E-Rechnung. Das ist eine normale PDF-Rechnung, bitte unter „Beleg erfassen“ ablegen.");
            }
            $via = 'pdf';
        } elseif (str_starts_with($head, '<')) {
            $file = [
                'content' => $content,
                'mime' => 'application/xml',
                'ext' => 'xml',
                'size' => strlen($content),
                'sha256' => hash('sha256', $content),
                'name' => $name,
            ];
            $xml = $content;
            $via = 'xml';
        } else {
            throw new EInvoiceException("„{$name}“ hat ein nicht erlaubtes Format. Erlaubt sind XRechnung (XML) und ZUGFeRD oder Factur-X (PDF).");
        }

        try {
            $parsed = EInvoiceParser::parse($xml);
        } catch (EInvoiceException $e) {
            throw new EInvoiceException("„{$name}“: ".$e->getMessage());
        }

        if ($via === 'pdf' && $parsed['format'] === 'E-Rechnung (CII)') {
            $parsed['format'] = 'ZUGFeRD / Factur-X';
        }

        return ['file' => $file, 'parsed' => $parsed, 'via' => $via];
    }

    /**
     * Legt aus der vorbereiteten Rechnung einen Beleg an.
     *
     * @param  array{file: array, parsed: array, via: string}  $prepared
     * @param  array{owner_id: int, owner_level: bool, property_id: ?int}  $target  bereits geprüfte Zuordnung
     * @return array{receipt: Receipt, warnings: string[], duplicate: ?Receipt, duplicate_invoice: ?Receipt}
     */
    public static function import(array $prepared, array $target, User $user): array
    {
        $p = $prepared['parsed'];
        $warnings = $p['warnings'];
        $notes = ['E-Rechnung ('.$p['format'].($prepared['via'] === 'pdf' ? ', aus dem PDF-Anhang gelesen' : '').')'];

        if ($p['is_credit']) {
            $notes[] = 'Gutschrift: Der Betrag steht mit Minus.';
        }
        if ($p['buyer_name'] !== null) {
            $notes[] = 'Rechnungsempfänger laut Rechnung: '.$p['buyer_name'];
        }

        $date = self::plausibleDate($p['issue_date']);
        if ($p['issue_date'] !== null && $date === null) {
            $warnings[] = 'Das Rechnungsdatum ('.self::german($p['issue_date']).') ist nicht plausibel und wurde nicht übernommen.';
        } elseif ($p['issue_date'] === null) {
            $warnings[] = 'In der Rechnung wurde kein Datum gefunden.';
        }

        $gross = $p['gross'];
        $net = $p['net'];
        $tax = $p['tax'];
        $currency = $p['currency'];
        if ($currency !== null && $currency !== 'EUR') {
            $notes[] = 'Die Rechnung lautet auf '.$currency.': Brutto '.($gross ?? '–').' '.$currency.'. Bitte den Betrag in Euro selbst eintragen.';
            $warnings[] = 'Die Rechnung ist nicht in Euro ('.$currency.'). Der Betrag wurde nicht übernommen.';
            $gross = $net = $tax = null;
        }

        foreach ($warnings as $w) {
            if (! in_array($w, ['In der Rechnung wurde kein Datum gefunden.'], true)) {
                $notes[] = 'Hinweis: '.$w;
            }
        }

        return DB::transaction(function () use ($prepared, $target, $user, $p, $date, $gross, $net, $tax, $notes, $warnings) {
            $vendor = EInvoiceVendors::match($p['seller']);

            $category = $vendor?->default_cost_category;
            if ($category !== null && ! in_array($category, Kostenarten::allowed(null), true)) {
                $category = null;
            }

            $data = [
                'owner_id' => (int) $target['owner_id'],
                'owner_level' => (bool) $target['owner_level'],
                'property_id' => $target['property_id'] ?: null,
                'unit_id' => null,
                'receipt_date' => $date,
                'amount_gross' => $gross,
                'cost_category' => $category,
                'vendor_name' => $vendor?->name,
                'note' => mb_substr(implode("\n", $notes), 0, 5000),
                'invoice_number' => $p['invoice_number'] !== null ? mb_substr($p['invoice_number'], 0, 80) : null,
                'due_date' => self::plausibleDate($p['due_date'], true),
                'net_amount' => $net,
                'tax_amount' => $tax,
                'source' => 'e_invoice',
                'einvoice_format' => mb_substr($p['format'], 0, 40),
            ];

            [$receipt, $duplicate] = ReceiptService::create($data, [$prepared['file']], $user);

            $duplicateInvoice = null;
            if ($receipt->invoice_number !== null && $receipt->vendor_id !== null) {
                $duplicateInvoice = Receipt::query()
                    ->visibleTo($user)
                    ->where('vendor_id', $receipt->vendor_id)
                    ->where('invoice_number', $receipt->invoice_number)
                    ->where('id', '!=', $receipt->id)
                    ->orderBy('id')
                    ->first();
            }

            return [
                'receipt' => $receipt,
                'warnings' => $warnings,
                'duplicate' => $duplicate,
                'duplicate_invoice' => $duplicateInvoice,
            ];
        });
    }

    private static function plausibleDate(?string $value, bool $allowFuture = false): ?string
    {
        if ($value === null) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            return null;
        }
        $limit = new DateTimeImmutable($allowFuture ? '+5 years' : '+1 year');
        if ($date < new DateTimeImmutable('2000-01-01') || $date > $limit) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private static function german(string $isoDate): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);

        return $d === false ? $isoDate : $d->format('d.m.Y');
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return $name === '' ? 'Datei' : mb_substr($name, 0, 150);
    }
}
