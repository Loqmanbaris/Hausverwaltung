<?php

namespace App\Support;

/** Beträge in deutscher Schreibweise lesen und anzeigen. */
class Money
{
    /**
     * Wandelt „1.234,56“, „1234,5“ oder „1234.56“ in „1234.56“ um.
     * Gibt null zurück, wenn der Text kein gültiger Betrag ist.
     */
    public static function parse(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $v = str_replace(["\u{00A0}", ' ', '€'], '', $v);
        $v = str_replace("\u{2212}", '-', $v);
        $v = preg_replace('/eur$/i', '', $v) ?? $v;

        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);
        }

        if (! preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $v)) {
            return null;
        }

        return number_format((float) $v, 2, '.', '');
    }

    /** 1234.5 wird zu „1.234,50 €“. */
    public static function format(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.').' €';
    }

    /** Für Eingabefelder: 1234.5 wird zu „1234,50“. */
    public static function input(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, ',', '');
    }
}
