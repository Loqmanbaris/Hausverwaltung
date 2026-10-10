<?php

namespace App\Support\Bank;

use App\Models\BankTransaction;
use App\Models\Lease;
use App\Models\RentCharge;
use Illuminate\Support\Facades\DB;

/**
 * Schlägt zu einer Gutschrift passende Mietverträge vor.
 * Punkte: bekanntes Zahlerkonto 60, Name des Mieters im Namen oder Verwendungszweck 30,
 * Betrag passt zu offener oder monatlicher Miete 25, Straße und Hausnummer des Objekts im Verwendungszweck 15.
 * Ab 85 Punkten gilt ein Vorschlag als sicher.
 */
class BankMatcher
{
    public const SURE = 85;

    public const MIN = 25;

    private const STOP = [
        'miete', 'mieten', 'mietanteil', 'garage', 'garagenmiete', 'nebenkosten', 'oktober', 'november', 'dezember', 'januar',
        'februar', 'maerz', 'april', 'juni', 'juli', 'august', 'september', 'gmbh', 'und', 'fuer', 'von', 'der', 'die',
        'das', 'mit', 'herr', 'frau', 'firma', 'e.k', 'inhaber', 'inhaberin', 'strasse', 'weg', 'platz', 'haus',
    ];

    public static function ibanHash(?string $iban): ?string
    {
        $iban = strtoupper((string) preg_replace('/\s+/', '', (string) $iban));
        if ($iban === '') {
            return null;
        }

        return hash_hmac('sha256', $iban, (string) config('app.key'));
    }

    /** Kleinbuchstaben, Umlaute umschreiben, „Str.“ zu „strasse“, nur Buchstaben und Ziffern. */
    public static function normalize(?string $s): string
    {
        $s = mb_strtolower((string) $s);
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'ç' => 'c', 'ş' => 's', 'ı' => 'i', 'ğ' => 'g']);
        $s = (string) preg_replace('/str\.(?=\s|\d|$)/', 'strasse ', $s);
        $s = (string) preg_replace('/(?<=[a-z])str(?=\s|\d|$)/', 'strasse', $s);
        $s = (string) preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim($s);
    }

    /** @return string[] bedeutungstragende Wörter (mindestens 3 Buchstaben, keine Füllwörter) */
    public static function tokens(?string $s): array
    {
        $out = [];
        foreach (explode(' ', self::normalize($s)) as $t) {
            if (mb_strlen($t) >= 3 && ! ctype_digit($t) && ! in_array($t, self::STOP, true)) {
                $out[$t] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Reine Bewertung.
     *
     * @param  array{amount: string, text: string, iban_hash: ?string}  $tx  text = Name und Verwendungszweck
     * @param  array{payer_hashes: string[], names: string[], streets: array<int, array{0: string, 1: string}>, amounts: string[]}  $lease
     * @return array{score: int, reasons: string[]}
     */
    public static function score(array $tx, array $lease): array
    {
        $score = 0;
        $reasons = [];
        $hay = ' '.self::normalize($tx['text']).' ';

        if ($tx['iban_hash'] !== null && in_array($tx['iban_hash'], $lease['payer_hashes'], true)) {
            $score += 60;
            $reasons[] = 'bekanntes Zahlerkonto';
        }

        foreach ($lease['names'] as $name) {
            $hit = array_filter(self::tokens($name), fn ($t) => mb_strlen($t) >= 4 && str_contains($hay, ' '.$t.' '));
            if ($hit !== []) {
                $score += 30;
                $reasons[] = 'Name passt';

                break;
            }
        }

        $amount = number_format((float) $tx['amount'], 2, '.', '');
        if (in_array($amount, $lease['amounts'], true)) {
            $score += 25;
            $reasons[] = 'Betrag passt';
        }

        foreach ($lease['streets'] as [$street, $number]) {
            if ($street !== '' && str_contains($hay, ' '.$street.' ') && ($number === '' || preg_match('/ '.preg_quote($street, '/').' '.preg_quote($number, '/').'( |[a-z] )/', $hay))) {
                $score += 15;
                $reasons[] = 'Anschrift';

                break;
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * Vorschläge für eine Gutschrift, beste zuerst.
     *
     * @return array<int, array{lease: Lease, score: int, reasons: string[]}>
     */
    public static function suggestions(BankTransaction $tx, ?array $leaseData = null): array
    {
        $leaseData ??= self::leaseData((int) $tx->owner_id, $tx->booking_date->format('Y-m-d'));

        $txData = [
            'amount' => (string) $tx->amount,
            'text' => trim(($tx->counterparty_name ?? '').' '.($tx->purpose ?? '')),
            'iban_hash' => $tx->counterparty_iban_hash,
        ];

        $out = [];
        foreach ($leaseData as $d) {
            $s = self::score($txData, $d);
            $out[] = ['lease' => $d['lease'], 'score' => $s['score'], 'reasons' => $s['reasons']];
        }
        usort($out, fn ($a, $b) => [$b['score'], $a['lease']->unit?->label] <=> [$a['score'], $b['lease']->unit?->label]);

        return $out;
    }

    /**
     * Daten der Verträge eines Eigentümers, die um das Buchungsdatum herum laufen.
     *
     * @return array<int, array>
     */
    public static function leaseData(int $ownerId, string $date): array
    {
        $from = date('Y-m-d', strtotime($date.' -62 days'));
        $to = date('Y-m-d', strtotime($date.' +35 days'));

        $leases = Lease::query()
            ->where('owner_id', $ownerId)
            ->where('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->with(['unit.property', 'tenant.contacts', 'rents'])
            ->get();

        $payers = DB::table('lease_payer_accounts')->whereIn('lease_id', $leases->pluck('id'))->get()->groupBy('lease_id');
        $open = RentCharge::query()->whereIn('lease_id', $leases->pluck('id'))->open()->get()->groupBy('lease_id');

        $out = [];
        foreach ($leases as $l) {
            $t = $l->tenant;
            $names = array_filter([
                $t?->display_name, $t?->company_name, trim(($t?->first_name ?? '').' '.($t?->last_name ?? '')),
            ]);
            foreach ($t?->contacts ?? [] as $c) {
                $names[] = $c->name;
            }

            $amounts = [];
            $rent = $l->currentRent($date);
            if ($rent) {
                $net = $rent->netTotal();
                $amounts[] = number_format(round($net + round($net * (float) $l->vat_rate) / 100, 2), 2, '.', '');
            }
            $charges = $open->get($l->id, collect());
            $amounts[] = number_format((float) $charges->sum(fn ($c) => $c->openAmount()), 2, '.', '');
            foreach ($charges as $c) {
                $amounts[] = number_format($c->openAmount(), 2, '.', '');
            }

            $streets = [];
            $street = self::normalize($l->unit?->property?->street);
            if ($street !== '' && preg_match('/^(.*?)\s+(\d+)\s*([a-z])?$/', $street, $m)) {
                $streets[] = [trim($m[1]), $m[2]];
            } elseif ($street !== '') {
                $streets[] = [$street, ''];
            }

            $out[] = [
                'lease' => $l,
                'payer_hashes' => $payers->get($l->id, collect())->pluck('iban_hash')->all(),
                'names' => array_values(array_unique($names)),
                'streets' => $streets,
                'amounts' => array_values(array_unique(array_filter($amounts, fn ($a) => (float) $a > 0))),
            ];
        }

        return $out;
    }
}
