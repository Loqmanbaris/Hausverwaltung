<?php

namespace App\Support\Datev;

use App\Models\DatevExport;
use App\Models\ReceiptFile;
use App\Support\Documents\DocumentStore;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Packt die Belege eines DATEV-Exports in eine ZIP-Datei. Jede Datei heißt nach Datum, Belegnummer
 * (wie Belegfeld 1 in der DATEV-Datei) und Beleg-Nr., dazu kommt eine Belegliste als CSV für Excel.
 * Die ZIP-Datei wird bei jedem Abruf aus den unveränderlichen Belegdateien neu erzeugt.
 */
class ReceiptArchive
{
    /** Mehr als das packen wir nicht in eine Datei. */
    public const MAX_BYTES = 250 * 1024 * 1024;

    /** Dateiname im ZIP: 2026-09-30_RE-2026-1_Beleg-12.pdf oder ..._Beleg-12_Seite-2.pdf */
    public static function entryName(string $date, string $doc, int $receiptId, int $page, int $pages, string $ext): string
    {
        $doc = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '-', $doc), '-. ');
        $doc = mb_substr($doc === '' ? 'ohne-Nummer' : $doc, 0, 60);

        return $date.'_'.$doc.'_Beleg-'.$receiptId.($pages > 1 ? '_Seite-'.$page : '').'.'.$ext;
    }

    /**
     * Belegliste als CSV (Semikolon, UTF-8 mit Kennung, damit Excel Umlaute richtig zeigt).
     *
     * @param  array<int, array{id: int, date: string, doc: string, vendor: string, text: string, account: string, side: string, amount: string, files: string[]}>  $rows
     */
    public static function listCsv(array $rows): string
    {
        $cell = fn (string $v) => '"'.str_replace('"', '""', trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v))).'"';

        $lines = [implode(';', array_map($cell, ['Beleg-Nr.', 'Belegdatum', 'Belegnummer (Belegfeld 1)', 'Lieferant', 'Buchungstext', 'Konto', 'Soll/Haben', 'Betrag in Euro', 'Datei(en)']))];
        foreach ($rows as $r) {
            $lines[] = implode(';', [
                $cell((string) $r['id']),
                $cell(DateTimeImmutable::createFromFormat('!Y-m-d', $r['date'])?->format('d.m.Y') ?? $r['date']),
                $cell($r['doc']),
                $cell($r['vendor']),
                $cell($r['text']),
                $cell($r['account']),
                $cell($r['side']),
                $cell(str_replace('.', ',', $r['amount'])),
                $cell(implode(' | ', $r['files'])),
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /**
     * Erzeugt die ZIP-Datei im temporären Ordner.
     *
     * @return array{path: string, name: string, count: int}
     *
     * @throws RuntimeException wenn eine Beleg-Datei fehlt, verändert wurde oder alles zu groß ist
     */
    public static function build(DatevExport $export): array
    {
        $items = DB::table('datev_export_items as i')
            ->join('receipts as r', 'r.id', '=', 'i.receipt_id')
            ->where('i.datev_export_id', $export->id)
            ->orderBy('i.receipt_id')
            ->get(['i.receipt_id', 'i.doc', 'i.entry_date', 'i.account', 'i.side', 'i.amount', 'i.text',
                'r.receipt_date', 'r.invoice_number', 'r.vendor_name', 'r.cost_category', 'r.amount_gross']);

        if ($items->isEmpty()) {
            throw new RuntimeException('Zu diesem Export gehören keine Belege.');
        }

        $files = ReceiptFile::query()
            ->whereIn('receipt_id', $items->pluck('receipt_id')->all())
            ->orderBy('receipt_id')
            ->orderBy('position')
            ->get()
            ->groupBy('receipt_id');

        $total = (int) $files->flatten()->sum('size_bytes');
        if ($total > self::MAX_BYTES) {
            throw new RuntimeException('Die Belege dieses Exports sind zusammen größer als '.intdiv(self::MAX_BYTES, 1024 * 1024).' MB und passen nicht in eine Datei. Bitte künftig kürzere Zeiträume exportieren.');
        }

        // Namen und Belegliste vorbereiten (ohne die Dateien zu lesen)
        $rows = [];
        $queue = [];
        foreach ($items as $i) {
            $id = (int) $i->receipt_id;
            $date = (string) ($i->entry_date ?? $i->receipt_date);
            $doc = (string) ($i->doc ?? '');
            if ($doc === '') {
                $doc = DatevFormat::documentNumber($i->invoice_number) ?: 'BELEG-'.$id;
            }
            $amount = $i->amount !== null ? (string) $i->amount : number_format(abs((float) $i->amount_gross), 2, '.', '');
            $side = (string) ($i->side ?? ((float) $i->amount_gross < 0 ? 'H' : 'S'));

            $list = $files->get($id, collect());
            $names = [];
            foreach ($list->values() as $n => $file) {
                $name = self::entryName($date, $doc, $id, $n + 1, $list->count(), $file->extension());
                $names[] = $name;
                $queue[] = [$name, $file, $id];
            }

            $rows[] = [
                'id' => $id,
                'date' => $date,
                'doc' => $doc,
                'vendor' => (string) $i->vendor_name,
                'text' => (string) ($i->text ?? ''),
                'account' => (string) ($i->account ?? ''),
                'side' => $side,
                'amount' => $amount,
                'files' => $names,
            ];
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'crm-belege-');
        $zip = new ZipWriter($path);

        try {
            $zip->addFile('Belegliste.csv', self::listCsv($rows));

            foreach ($queue as [$name, $file, $receiptId]) {
                try {
                    $plain = DocumentStore::get($file->storage_path);
                } catch (RuntimeException $e) {
                    throw new RuntimeException('Die Datei von Beleg Nr. '.$receiptId.' kann nicht gelesen werden: '.$e->getMessage());
                }
                if (! hash_equals((string) $file->sha256, hash('sha256', $plain))) {
                    throw new RuntimeException('Die Datei von Beleg Nr. '.$receiptId.' ist verändert worden und wird nicht ausgeliefert.');
                }
                $zip->addFile($name, $plain);
                unset($plain);
            }
            $zip->close();
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e instanceof RuntimeException ? $e : new RuntimeException('Die ZIP-Datei konnte nicht erstellt werden: '.$e->getMessage());
        }

        return [
            'path' => $path,
            'name' => 'Belege_Export-'.$export->id.'_'.$export->period_from->format('Y-m-d').'_bis_'.$export->period_to->format('Y-m-d').'.zip',
            'count' => count($queue),
        ];
    }
}
