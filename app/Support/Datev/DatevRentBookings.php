<?php

namespace App\Support\Datev;

use App\Support\Rent\RentMath;

/**
 * Macht aus Mietzahlungen die Buchungen des Exports. Reine Rechenlogik ohne Datenbank.
 *
 * Gebucht wird der Zahlungseingang (Zuflussprinzip). Eine Zahlung wird nach den Monaten aufgeteilt, die sie bezahlt,
 * und innerhalb des Monats nach Bestandteilen (Miete, Nebenkosten, Heizkosten) im Verhältnis der Bruttobeträge.
 * Ein Rest ohne Soll (Vorauszahlung, Guthaben) und Rückbuchungen gehen auf „Miete“.
 * Erlöse werden im Haben auf das Erlöskonto gebucht, Gegenkonto ist Bank oder Kasse. Umsatzsteuer wird wie bei den
 * Belegen nicht aufgeteilt: gebucht wird brutto, ein Steuerschlüssel kommt nur, wenn er beim Konto hinterlegt ist.
 */
class DatevRentBookings
{
    /**
     * Aufteilung einer Zahlung auf die Teile miete, nk, heiz (Bruttobeträge, Summe = Zahlung).
     *
     * @param  array<int, array{amount: string, charge: array<string, mixed>}>  $allocations  charge mit Bestandteilen (netto) und vat_rate
     * @return array<string, string> Teil => Betrag
     */
    public static function split(string $paymentAmount, array $allocations): array
    {
        $total = RentMath::cents($paymentAmount);
        $parts = ['miete' => 0, 'nk' => 0, 'heiz' => 0];
        if ($total <= 0) {
            $parts['miete'] = $total;

            return array_map([RentMath::class, 'money'], $parts);
        }

        $used = 0;
        foreach ($allocations as $a) {
            $cents = RentMath::cents($a['amount']);
            $rate = (float) ($a['charge']['vat_rate'] ?? 0);
            $weights = [];
            foreach (DatevRentAccounts::COMPONENT_PART as $col => $part) {
                $net = RentMath::cents($a['charge'][$col] ?? 0);
                $weights[$part] = ($weights[$part] ?? 0) + $net + (int) round($net * $rate / 100);
            }
            foreach (self::distribute($cents, $weights) as $part => $c) {
                $parts[$part] += $c;
            }
            $used += $cents;
        }
        $parts['miete'] += $total - $used;

        return array_map([RentMath::class, 'money'], $parts);
    }

    /**
     * Verteilt Cent-Beträge nach Gewichten (größter Rest zuerst), die Summe bleibt exakt.
     *
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    public static function distribute(int $cents, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return ['miete' => $cents];
        }
        $out = [];
        $rest = [];
        $given = 0;
        foreach ($weights as $k => $w) {
            $exact = $cents * $w / $sum;
            $out[$k] = (int) floor($exact);
            $rest[$k] = $exact - $out[$k];
            $given += $out[$k];
        }
        arsort($rest);
        foreach (array_keys($rest) as $k) {
            if ($given >= $cents) {
                break;
            }
            $out[$k]++;
            $given++;
        }

        return $out;
    }

    /**
     * @param  array<int, array{id: int, date: string, amount: string, method: string, group: string, label: string, parts: array<string, string>}>  $payments
     * @param  array<string, array{account: ?string, bu_key: ?string}>  $accounts
     * @param  array{bank: string, cash: string}  $counters
     * @return array{bookings: array<int, array>, skipped: array<int, array{id: int, reason: string}>}
     */
    public static function plan(array $payments, array $accounts, array $counters, int $accountLength): array
    {
        $bookings = [];
        $skipped = [];

        foreach ($payments as $p) {
            $counter = $p['method'] === 'bar' && $counters['cash'] !== '' ? $counters['cash'] : $counters['bank'];

            $lines = [];
            $problem = null;
            foreach ($p['parts'] as $part => $amount) {
                $cents = RentMath::cents($amount);
                if ($cents === 0) {
                    continue;
                }
                $acc = DatevRentAccounts::resolve($accounts, $p['group'], $part);
                if ($acc === null) {
                    $problem = 'Für „'.DatevRentAccounts::groupLabel($p['group']).'“ ist noch kein Erlöskonto eingetragen.';

                    break;
                }
                if (! DatevConfig::validAccount($acc['account'], $accountLength)) {
                    $problem = 'Das Erlöskonto „'.$acc['account'].'“ passt nicht zur Sachkontenlänge '.$accountLength.'.';

                    break;
                }
                $k = $acc['account'].'|'.$acc['bu_key'];
                $lines[$k] ??= ['account' => $acc['account'], 'bu_key' => $acc['bu_key'], 'key' => $acc['key'], 'cents' => 0, 'parts' => []];
                $lines[$k]['cents'] += $cents;
                $lines[$k]['parts'][] = $part;
            }
            if ($problem === null && $counter === '') {
                $problem = 'Das Gegenkonto für Mieteinnahmen (Bank) fehlt.';
            }
            if ($problem !== null) {
                $skipped[] = ['id' => $p['id'], 'reason' => $problem];

                continue;
            }

            foreach ($lines as $l) {
                if ($l['cents'] === 0) {
                    continue;
                }
                $partText = count($l['parts']) === 1 ? ['miete' => 'Miete', 'nk' => 'NK-Vorausz.', 'heiz' => 'HK-Vorausz.'][$l['parts'][0]] : 'Miete';
                $bookings[] = [
                    'payment_id' => $p['id'],
                    'date' => $p['date'],
                    'amount' => RentMath::money(abs($l['cents'])),
                    'side' => $l['cents'] > 0 ? 'H' : 'S',
                    'account' => $l['account'],
                    'counter' => $counter,
                    'bu_key' => $l['bu_key'],
                    'rent_key' => $l['key'],
                    'doc' => 'MIETE-'.$p['id'],
                    'text' => $partText.' '.$p['label'],
                ];
            }
        }

        usort($bookings, fn ($a, $b) => [$a['date'], $a['payment_id'], $a['account']] <=> [$b['date'], $b['payment_id'], $b['account']]);

        return ['bookings' => $bookings, 'skipped' => $skipped];
    }
}
