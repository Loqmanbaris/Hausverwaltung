<?php

namespace App\Support\Import;

use App\Support\Datev\ZipWriter;

/** Schreibt eine einfache Excel-Datei (.xlsx) mit mehreren Blättern, Kopfzeile fett, Spaltenbreiten. */
class XlsxWriter
{
    /**
     * @param  array<string, array{rows: array<int, array<int, string>>, widths?: int[], header?: bool}>  $sheets
     */
    public static function write(string $path, array $sheets): void
    {
        $zip = new ZipWriter($path);
        $names = array_keys($sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($names as $i => $n) {
            $ct .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFile('[Content_Types].xml', $ct.'</Types>');
        $zip->addFile('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($names as $i => $n) {
            $wb .= '<sheet name="'.self::esc($n).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
            $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.(count($names) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $zip->addFile('xl/workbook.xml', $wb.'</sheets></workbook>');
        $zip->addFile('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFile('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDCE6F1"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Standard" xfId="0" builtinId="0"/></cellStyles></styleSheet>');

        foreach ($names as $i => $n) {
            $s = $sheets[$n];
            $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
            if (! empty($s['header'])) {
                $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
            }
            if (! empty($s['widths'])) {
                $x .= '<cols>';
                foreach ($s['widths'] as $ci => $w) {
                    // Datenblätter: Spalten als Text formatieren, damit Excel nichts umwandelt (z. B. PLZ mit 0)
                    $x .= '<col min="'.($ci + 1).'" max="'.($ci + 1).'" width="'.$w.'" customWidth="1"'.(! empty($s['header']) ? ' style="2"' : '').'/>';
                }
                $x .= '</cols>';
            }
            $x .= '<sheetData>';
            foreach ($s['rows'] as $ri => $row) {
                $x .= '<row r="'.($ri + 1).'">';
                foreach ($row as $ci => $val) {
                    $style = ! empty($s['header']) && $ri === 0 ? ' s="1"' : (! empty($s['bold'][$ri]) ? ' s="1"' : '');
                    $x .= '<c r="'.XlsxReader::colName($ci + 1).($ri + 1).'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::esc((string) $val).'</t></is></c>';
                }
                $x .= '</row>';
            }
            $zip->addFile('xl/worksheets/sheet'.($i + 1).'.xml', $x.'</sheetData></worksheet>');
        }
        $zip->close();
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
