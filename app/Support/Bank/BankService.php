<?php

namespace App\Support\Bank;

use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Lease;
use App\Models\LeasePayerAccount;
use App\Models\Owner;
use App\Models\OwnerBankAccount;
use App\Models\RentPayment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use App\Support\Stammdaten;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Kontoauszug einlesen, Gutschriften Mietverträgen zuordnen, Zuordnung zurücknehmen. */
class BankService
{
    /**
     * Sucht die Bankverbindung zu einer IBAN unter den Eigentümern, die der Benutzer sehen darf.
     */
    public static function findAccount(string $iban, User $user): ?OwnerBankAccount
    {
        $iban = Stammdaten::normalizeIban($iban);
        $ownerIds = Owner::visibleTo($user)->pluck('owners.id');
        $found = OwnerBankAccount::query()->whereIn('owner_id', $ownerIds)->get()
            ->filter(fn ($a) => Stammdaten::normalizeIban($a->iban) === $iban)
            ->values();

        if ($found->count() > 1) {
            throw new BankImportException('Das Konto ist bei mehreren Eigentümern als Bankverbindung hinterlegt. Bitte nur beim richtigen Eigentümer stehen lassen.');
        }

        return $found->first();
    }

    /**
     * Liest die Datei ein und legt neue Umsätze an. Schon vorhandene werden übersprungen.
     *
     * @throws BankImportException
     */
    public static function import(string $raw, string $filename, ?int $accountId, User $user): BankImport
    {
        $parsed = BankCsvParser::parse($raw);

        if ($parsed['account_iban'] !== null) {
            $account = self::findAccount($parsed['account_iban'], $user);
            if ($account === null) {
                throw new BankImportException('Das Konto '.trim(chunk_split($parsed['account_iban'], 4, ' ')).' ist bei keinem Eigentümer als Bankverbindung hinterlegt. Bitte zuerst beim Eigentümer unter „Bankverbindungen“ eintragen.');
            }
            if ($accountId && (int) $account->id !== $accountId) {
                throw new BankImportException('Die Datei gehört zum Konto '.$account->formattedIban().', nicht zum ausgewählten Konto.');
            }
        } else {
            $account = $accountId ? OwnerBankAccount::query()->whereIn('owner_id', Owner::visibleTo($user)->pluck('owners.id'))->find($accountId) : null;
            if ($account === null) {
                throw new BankImportException('In der Datei steht kein Auftragskonto. Bitte oben das Konto auswählen.');
            }
        }
        $ownIban = Stammdaten::normalizeIban($account->iban);

        $stored = null;
        try {
            $stored = DocumentStore::put($raw);
        } catch (Throwable) {
            $stored = null; // Die Umsätze sind wichtiger als die Ablage der Originaldatei
        }

        try {
            return DB::transaction(function () use ($parsed, $filename, $account, $user, $stored, $ownIban) {
                $import = BankImport::create([
                    'owner_id' => $account->owner_id,
                    'owner_bank_account_id' => $account->id,
                    'filename' => mb_substr($filename, 0, 190),
                    'format' => $parsed['format'],
                    'storage_path' => $stored,
                    'period_from' => $parsed['from'],
                    'period_to' => $parsed['to'],
                    'rows_total' => count($parsed['rows']),
                    'rows_skipped' => $parsed['skipped'],
                    'created_by' => $user->id,
                ]);

                $existing = DB::table('bank_transactions')->where('owner_bank_account_id', $account->id)
                    ->whereIn('dedupe_key', array_column($parsed['rows'], 'dedupe_key'))->pluck('dedupe_key')->flip();

                $new = 0;
                foreach ($parsed['rows'] as $r) {
                    if ($existing->has($r['dedupe_key'])) {
                        continue;
                    }
                    [$status, $note] = self::classify($r, $ownIban);
                    BankTransaction::create([
                        'bank_import_id' => $import->id,
                        'owner_id' => $account->owner_id,
                        'owner_bank_account_id' => $account->id,
                        'dedupe_key' => $r['dedupe_key'],
                        'booking_date' => $r['booking_date'],
                        'value_date' => $r['value_date'],
                        'amount' => $r['amount'],
                        'currency' => mb_substr($r['currency'], 0, 3),
                        'booking_text' => $r['booking_text'],
                        'purpose' => $r['purpose'],
                        'counterparty_name' => $r['name'],
                        'counterparty_iban' => $r['iban'],
                        'counterparty_iban_hash' => BankMatcher::ibanHash($r['iban']),
                        'counterparty_bic' => $r['bic'],
                        'end_to_end' => $r['end_to_end'],
                        'mandate_ref' => $r['mandate_ref'],
                        'creditor_id' => $r['creditor_id'],
                        'status' => $status,
                        'status_note' => $note,
                    ]);
                    $new++;
                }

                $import->update(['rows_new' => $new, 'rows_duplicate' => count($parsed['rows']) - $new]);
                // Darlehensraten automatisch zuordnen (Paket 23)
                if ($new > 0 && \Illuminate\Support\Facades\Schema::hasTable('loans')) {
                    \App\Support\Finance\LoanMatcher::autoAssign((int) $account->owner_id, \App\Models\BankTransaction::query()->where('bank_import_id', $import->id)->pluck('id')->all());
                }
                Audit::log('bank.imported', $import, 'Kontoauszug eingelesen: '.$filename.', '.$new.' neue Umsätze, '.(count($parsed['rows']) - $new).' schon vorhanden');

                return $import;
            });
        } catch (Throwable $e) {
            if ($stored) {
                DocumentStore::forget($stored);
            }

            throw $e;
        }
    }

    /**
     * Grobe Einordnung beim Einlesen: Belastungen sind Ausgaben, zurückgegebene Lastschriften keine Miete.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function classify(array $row, string $ownIban): array
    {
        if ((float) $row['amount'] < 0) {
            return ['ausgabe', null];
        }
        $text = mb_strtoupper((string) $row['booking_text']);
        if (str_contains($text, 'WIEDERGUTSCHRIFT') || str_contains($text, 'RUECKLASTSCHRIFT') || str_contains($text, 'RÜCKLASTSCHRIFT')
            || ($row['iban'] !== null && $row['iban'] === $ownIban)) {
            return ['ignoriert', 'Rückbuchung einer Lastschrift (automatisch erkannt)'];
        }

        return ['offen', null];
    }

    /**
     * Ordnet (einen Teil) einer Gutschrift einem Mietvertrag zu.
     *
     * @throws BankImportException
     */
    public static function assign(BankTransaction $tx, Lease $lease, string $amount, bool $remember, User $user): RentPayment
    {
        if ((int) $lease->owner_id !== (int) $tx->owner_id) {
            throw new BankImportException('Der Mietvertrag gehört zu einem anderen Eigentümer als das Konto.');
        }
        if (! in_array($tx->status, ['offen', 'teilweise'], true)) {
            throw new BankImportException('Dieser Umsatz ist schon erledigt.');
        }
        $value = (float) $amount;
        $remaining = $tx->remaining();
        if ($value <= 0 || $value - $remaining > 0.004) {
            throw new BankImportException('Der Betrag muss größer als 0 und höchstens '.Money::format($remaining).' sein.');
        }

        $lease->loadMissing(['unit.property', 'tenant']);

        $payment = DB::transaction(function () use ($tx, $lease, $amount, $remember, $user) {
            $payment = RentPayment::create([
                'lease_id' => $lease->id,
                'owner_id' => $lease->owner_id,
                'paid_on' => $tx->booking_date->format('Y-m-d'),
                'amount' => $amount,
                'method' => $tx->mandate_ref ? 'lastschrift' : 'ueberweisung',
                'source' => 'bank',
                'bank_transaction_id' => $tx->id,
                'reference' => $tx->purpose ? mb_substr($tx->purpose, 0, 190) : null,
                'note' => $tx->counterparty_name ? mb_substr('Kontoauszug: '.$tx->counterparty_name, 0, 2000) : 'Kontoauszug',
                'created_by' => $user->id,
            ]);

            if ($remember && $tx->counterparty_iban_hash) {
                LeasePayerAccount::query()->firstOrCreate(
                    ['lease_id' => $lease->id, 'iban_hash' => $tx->counterparty_iban_hash],
                    ['iban' => $tx->counterparty_iban, 'name' => $tx->counterparty_name, 'created_by' => $user->id]
                );
            }

            self::refreshStatus($tx);
            Audit::log('bank.assigned', $payment, 'Kontoumsatz zugeordnet: '.Money::format($amount).' vom '.$tx->booking_date->format('d.m.Y').' an '.$lease->title());

            return $payment;
        });

        RentLedger::sync($lease);

        return $payment;
    }

    public static function ignore(BankTransaction $tx, string $note): void
    {
        if ($tx->payments()->exists()) {
            throw new BankImportException('Zu diesem Umsatz sind schon Zahlungen zugeordnet. Bitte zuerst „Zurücksetzen“.');
        }
        $tx->update(['status' => 'ignoriert', 'status_note' => mb_substr($note !== '' ? $note : 'keine Miete', 0, 190)]);
        Audit::log('bank.ignored', $tx, 'Kontoumsatz als „keine Miete“ markiert: '.Money::format($tx->amount).' vom '.$tx->booking_date->format('d.m.Y'));
    }

    /** Nimmt alle Zuordnungen zurück (Zahlungen werden storniert) und setzt den Umsatz wieder auf offen. */
    public static function reset(BankTransaction $tx): void
    {
        $leases = [];
        DB::transaction(function () use ($tx, &$leases) {
            DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->delete();
            foreach ($tx->payments()->get() as $p) {
                $leases[$p->lease_id] = true;
                $p->delete();
            }
            $tx->update([
                'status' => (float) $tx->amount < 0 ? 'ausgabe' : 'offen',
                'status_note' => null,
            ]);
            Audit::log('bank.reset', $tx, 'Zuordnung zurückgesetzt: '.Money::format($tx->amount).' vom '.$tx->booking_date->format('d.m.Y'));
        });

        foreach (array_keys($leases) as $id) {
            $lease = Lease::query()->find($id);
            if ($lease) {
                RentLedger::sync($lease);
            }
        }
    }

    /** Status nach den zugeordneten Zahlungen setzen (nach Zuordnung oder Storno einer Zahlung). */
    public static function refreshStatus(BankTransaction $tx): void
    {
        if ((float) $tx->amount < 0 || $tx->status === 'ignoriert') {
            return;
        }
        $assigned = (float) $tx->payments()->sum('amount');
        $status = $assigned <= 0.004 ? 'offen' : ((float) $tx->amount - $assigned > 0.004 ? 'teilweise' : 'zugeordnet');
        if ($status !== $tx->status) {
            $tx->update(['status' => $status]);
        }
    }

    public static function openCountFor(User $user): int
    {
        try {
            return (int) BankTransaction::visibleTo($user)->whereIn('status', ['offen', 'teilweise'])->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
