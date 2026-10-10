<?php

namespace App\Support\Datev;

use DateTimeImmutable;
use InvalidArgumentException;

/** Erzeugt die DATEV-Datei (Buchungsstapel) aus den geplanten Buchungen. */
class DatevExporter
{
    /**
     * @param  array{consultant: string, client: string, skr: string, fy_month: int, account_length: int}  $config
     * @param  array<int, array>  $bookings  Ergebnis von DatevBookings::plan()
     * @return array{content: string, from: string, to: string, count: int, sum: string, filename: string, fy_start: string}
     */
    public static function build(array $config, array $bookings, DateTimeImmutable $now, string $label = 'Belege'): array
    {
        if ($bookings === []) {
            throw new InvalidArgumentException('Es gibt keine Buchungen für den Export.');
        }

        $dates = array_column($bookings, 'date');
        $from = min($dates);
        $to = max($dates);

        $fyStart = DatevConfig::fiscalYearStart(new DateTimeImmutable($from), $config['fy_month']);
        $fyEnd = DatevConfig::fiscalYearEnd($fyStart);
        if (new DateTimeImmutable($to) > $fyEnd) {
            throw new InvalidArgumentException('Die Belege liegen in verschiedenen Wirtschaftsjahren. Bitte je Wirtschaftsjahr getrennt exportieren.');
        }

        $lines = [
            DatevFormat::headerLine([
                'consultant' => $config['consultant'],
                'client' => $config['client'],
                'fy_start' => $fyStart->format('Y-m-d'),
                'account_length' => $config['account_length'],
                'from' => $from,
                'to' => $to,
                'description' => $label.' '.substr($from, 0, 7).($from !== $to && substr($from, 0, 7) !== substr($to, 0, 7) ? ' bis '.substr($to, 0, 7) : ''),
                'skr' => $config['skr'],
                'created' => $now,
            ]),
            DatevFormat::columnHeaderLine(),
        ];

        $sum = 0.0;
        foreach ($bookings as $b) {
            $lines[] = DatevFormat::row([
                DatevFormat::F_AMOUNT => DatevFormat::amount($b['amount']),
                DatevFormat::F_DEBIT_CREDIT => '"'.$b['side'].'"',
                DatevFormat::F_CURRENCY => '"EUR"',
                DatevFormat::F_ACCOUNT => $b['account'],
                DatevFormat::F_COUNTER_ACCOUNT => $b['counter'],
                DatevFormat::F_TAX_KEY => DatevFormat::text($b['bu_key'], 4),
                DatevFormat::F_DOC_DATE => DatevFormat::ttmm($b['date']),
                DatevFormat::F_DOC_FIELD_1 => DatevFormat::text($b['doc'], 36),
                DatevFormat::F_TEXT => DatevFormat::text($b['text'], 60),
                DatevFormat::F_LOCK => '0',
            ]);
            $sum += $b['side'] === 'H' ? -(float) $b['amount'] : (float) $b['amount'];
        }

        return [
            'content' => DatevFormat::encode($lines),
            'from' => $from,
            'to' => $to,
            'count' => count($bookings),
            'sum' => number_format($sum, 2, '.', ''),
            'filename' => 'EXTF_Buchungsstapel_'.($label === 'Belege' ? '' : $label.'_').$now->format('Ymd-His').'.csv',
            'fy_start' => $fyStart->format('Y-m-d'),
        ];
    }
}
