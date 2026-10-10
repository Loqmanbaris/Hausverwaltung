<?php

namespace App\Support\Nk;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Property;
use DateTimeImmutable;

/** Zählerstände aus CSV: Zählernummer; Datum; Stand (Paket 26). */
class MeterImport
{
    /** @return array{saved: int, unknown: string[], invalid: int} */
    public static function import(Property $property, string $csv, ?int $userId): array
    {
        if (! mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $first = '';
        foreach ($lines as $l) {
            if (trim($l) !== '') {
                $first = $l;
                break;
            }
        }
        $delim = substr_count($first, ';') > 0 ? ';' : (substr_count($first, "\t") > 0 ? "\t" : ',');

        $meters = [];
        foreach (Meter::query()->where('property_id', $property->id)->whereNotNull('number')->get() as $m) {
            $meters[self::norm((string) $m->number)] = $m;
        }

        $saved = 0;
        $invalid = 0;
        $unknown = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map(fn ($c) => trim((string) $c, " \t\""), str_getcsv($line, $delim, '"', ''));
            if (count($cols) < 3) {
                $invalid++;

                continue;
            }
            [$number, $date, $value] = $cols;
            $d = self::date($date);
            $v = self::number($value);
            if ($d === null || $v === null) {
                // Kopfzeile still überspringen
                if ($saved === 0 && $invalid === 0 && $unknown === [] && $d === null) {
                    continue;
                }
                $invalid++;

                continue;
            }
            $m = $meters[self::norm($number)] ?? null;
            if ($m === null) {
                $unknown[$number] = $number;

                continue;
            }
            MeterReading::query()->updateOrCreate(['meter_id' => $m->id, 'read_on' => $d], ['value' => $v, 'note' => 'Import', 'created_by' => $userId]);
            $saved++;
        }

        return ['saved' => $saved, 'unknown' => array_values($unknown), 'invalid' => $invalid];
    }

    private static function norm(string $n): string
    {
        return mb_strtolower(preg_replace('/[\s\-\/.]/u', '', $n) ?? $n);
    }

    public static function date(string $v): ?string
    {
        $v = trim(explode(' ', trim($v))[0] ?? '');
        foreach (['!d.m.Y', '!Y-m-d', '!d.m.y', '!d/m/Y'] as $f) {
            $d = DateTimeImmutable::createFromFormat($f, $v);
            if ($d !== false && $d->format(ltrim($f, '!')) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    public static function number(string $v): ?string
    {
        $v = str_replace(["\u{00A0}", ' '], '', trim($v));
        if (str_contains($v, ',')) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        }
        if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $v)) {
            return null;
        }

        return $v;
    }
}
