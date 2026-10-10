<?php

namespace App\Support\Datev;

/**
 * Macht aus erfassten Belegen die Buchungen des Exports. Reine Rechenlogik ohne Datenbank.
 *
 * Eine Rechnung wird im Soll auf das Konto der Kostenart gebucht, das Gegenkonto steht in den Einstellungen.
 * Eine Gutschrift (negativer Betrag) wird im Haben auf dasselbe Konto gebucht. Umsatzsteuer wird nicht aufgeteilt:
 * Es wird der Bruttobetrag gebucht, ein Steuerschlüssel kommt nur, wenn er für die Kostenart hinterlegt ist.
 */
class DatevBookings
{
    /**
     * @param  array<int, array{id: int, date: string, amount: string, category: string, category_label: string, vendor: ?string, invoice_number: ?string}>  $receipts
     * @param  array<string, array{account: ?string, bu_key: ?string}>  $accounts  Kostenart-Schlüssel => Konto
     * @param  array{account_length: int, counter_account: string}  $config
     * @return array{bookings: array<int, array>, skipped: array<int, array{id: int, reason: string}>}
     */
    public static function plan(array $receipts, array $accounts, array $config): array
    {
        $bookings = [];
        $skipped = [];

        foreach ($receipts as $r) {
            $amount = (float) $r['amount'];
            if (abs($amount) < 0.005) {
                $skipped[] = ['id' => $r['id'], 'reason' => 'Der Betrag ist 0,00 €.'];

                continue;
            }
            if (abs($amount) >= 10_000_000_000) {
                $skipped[] = ['id' => $r['id'], 'reason' => 'Der Betrag ist zu groß für das DATEV-Format.'];

                continue;
            }

            $map = $accounts[$r['category']] ?? null;
            $account = trim((string) ($map['account'] ?? ''));
            if ($account === '') {
                $skipped[] = ['id' => $r['id'], 'reason' => 'Für die Kostenart „'.$r['category_label'].'“ ist noch kein Konto zugeordnet.'];

                continue;
            }
            if (! DatevConfig::validAccount($account, $config['account_length'])) {
                $skipped[] = ['id' => $r['id'], 'reason' => 'Das Konto „'.$account.'“ der Kostenart „'.$r['category_label'].'“ passt nicht zur Sachkontenlänge '.$config['account_length'].'.'];

                continue;
            }

            $doc = DatevFormat::documentNumber($r['invoice_number']);
            if ($doc === '') {
                $doc = 'BELEG-'.$r['id'];
            }

            $label = trim((string) $r['vendor']) !== '' ? trim((string) $r['vendor']) : 'Beleg Nr. '.$r['id'];

            $bookings[] = [
                'receipt_id' => $r['id'],
                'date' => $r['date'],
                'amount' => number_format(abs($amount), 2, '.', ''),
                'side' => $amount < 0 ? 'H' : 'S',
                'account' => $account,
                'counter' => $config['counter_account'],
                'bu_key' => trim((string) ($map['bu_key'] ?? '')),
                'doc' => $doc,
                'text' => $label.', '.$r['category_label'],
            ];
        }

        usort($bookings, fn ($a, $b) => [$a['date'], $a['receipt_id']] <=> [$b['date'], $b['receipt_id']]);

        return ['bookings' => $bookings, 'skipped' => $skipped];
    }
}
