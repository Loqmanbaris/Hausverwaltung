<?php

namespace App\Support\Bank;

use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionItem;
use App\Models\DepositEntry;
use App\Models\Lease;
use App\Models\LeasePayerAccount;
use App\Models\Owner;
use App\Models\OwnerBankAccount;
use App\Models\RentPayment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Kaution\Kaution;
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
        $stored = null;
        try {
            $stored = DocumentStore::put($raw);
        } catch (Throwable) {
            $stored = null; // Die Umsätze sind wichtiger als die Ablage der Originaldatei
        }

        return self::importParsed($parsed, $filename, $account, $user, $stored);
    }

    /**
     * Legt gelesene Umsätze an (aus der CSV-Datei oder aus dem automatischen Kontoabruf). Schon vorhandene werden übersprungen.
     *
     * @param  array{format: string, rows: array<int, array>, skipped: int, from: ?string, to: ?string}  $parsed
     */
    public static function importParsed(array $parsed, string $filename, OwnerBankAccount $account, ?User $user, ?string $stored = null): BankImport
    {
        $ownIban = Stammdaten::normalizeIban((string) $account->iban);

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
                    'created_by' => $user?->id,
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
                Audit::log('bank.imported', $import, ($parsed['format'] === 'FinTS' ? 'Kontoabruf: ' : 'Kontoauszug eingelesen: ').$filename.', '.$new.' neue Umsätze, '.(count($parsed['rows']) - $new).' schon vorhanden', label: $user ? null : 'Kontoabruf');

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

    /**
     * Gutschrift aufteilen (wie bei Objego): jede Zeile mit Einnahmeart, Mietvertrag, Monat, Betrag und Notiz.
     * Miete, Nebenkosten und Gebühren werden Mietzahlungen (Miete auf den gewählten Monat), Kaution geht zur Kaution,
     * Sonstiges wird nur vermerkt. Die Summe darf den offenen Rest nicht überschreiten.
     *
     * @param  array<int, array{kind?: string, lease_id?: mixed, month?: ?string, amount?: ?string, note?: ?string}>  $rows
     * @return array{count: int, sum: float}
     *
     * @throws BankImportException
     */
    public static function split(BankTransaction $tx, array $rows, bool $remember, User $user): array
    {
        if (! in_array($tx->status, ['offen', 'teilweise'], true)) {
            throw new BankImportException('Dieser Umsatz ist schon erledigt.');
        }
        $clean = [];
        foreach ($rows as $n => $row) {
            $amount = Money::parse((string) ($row['amount'] ?? ''));
            $kind = (string) ($row['kind'] ?? 'miete');
            $leaseId = (int) ($row['lease_id'] ?? 0);
            $note = trim((string) ($row['note'] ?? ''));
            if ($amount === null && $leaseId === 0 && $note === '') {
                continue;   // leere Zeile
            }
            $line = 'Zeile '.($n + 1).': ';
            if (! array_key_exists($kind, RentPayment::INCOME_KINDS)) {
                throw new BankImportException($line.'Bitte eine Einnahmeart wählen.');
            }
            if ($amount === null || (float) $amount <= 0) {
                throw new BankImportException($line.'Bitte einen Betrag größer als 0 eintragen.');
            }
            $lease = $leaseId > 0 ? Lease::query()->find($leaseId) : null;
            if ($kind !== 'sonstiges' && ! $lease) {
                throw new BankImportException($line.'Bitte den Mietvertrag wählen.');
            }
            if ($lease && (int) $lease->owner_id !== (int) $tx->owner_id) {
                throw new BankImportException($line.'Der Mietvertrag gehört zu einem anderen Eigentümer als das Konto.');
            }
            if ($kind === 'sonstiges' && $note === '') {
                throw new BankImportException($line.'Bitte bei „Sonstiges“ kurz notieren, wofür das Geld ist (z. B. Strom, Privates).');
            }
            $month = trim((string) ($row['month'] ?? ''));
            if ($kind !== 'miete' || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
                $month = '';
            }
            $clean[] = ['kind' => $kind, 'lease' => $lease, 'month' => $month, 'amount' => $amount, 'note' => mb_substr($note, 0, 190)];
        }
        if ($clean === []) {
            throw new BankImportException('Bitte mindestens eine Zeile mit Betrag ausfüllen.');
        }
        $sum = round(array_sum(array_map(fn ($r) => (float) $r['amount'], $clean)), 2);
        $remaining = $tx->remaining();
        if ($sum - $remaining > 0.004) {
            throw new BankImportException('Die Summe ('.Money::format($sum).') ist höher als der offene Betrag ('.Money::format($remaining).').');
        }

        $leases = [];
        DB::transaction(function () use ($tx, $clean, $remember, $user, &$leases) {
            $method = $tx->mandate_ref ? 'lastschrift' : 'ueberweisung';
            $from = $tx->counterparty_name ? 'Kontoauszug: '.$tx->counterparty_name : 'Kontoauszug';
            $remembered = false;
            foreach ($clean as $r) {
                $lease = $r['lease'];
                $lease?->loadMissing(['unit.property', 'tenant']);
                $text = RentPayment::INCOME_KINDS[$r['kind']].($r['month'] !== '' ? ' '.substr($r['month'], 5, 2).'/'.substr($r['month'], 0, 4) : '');

                if (in_array($r['kind'], RentPayment::RENT_KINDS, true)) {
                    $payment = RentPayment::create([
                        'lease_id' => $lease->id,
                        'owner_id' => $lease->owner_id,
                        'paid_on' => $tx->booking_date->format('Y-m-d'),
                        'amount' => $r['amount'],
                        'method' => $method,
                        'source' => 'bank',
                        'income_kind' => $r['kind'],
                        'for_period' => $r['month'] !== '' ? $r['month'].'-01' : null,
                        'bank_transaction_id' => $tx->id,
                        'reference' => $tx->purpose ? mb_substr($tx->purpose, 0, 190) : null,
                        'note' => mb_substr($r['note'] !== '' ? $r['note'].' ('.$from.')' : $from, 0, 2000),
                        'created_by' => $user->id,
                    ]);
                    $leases[$lease->id] = $lease;
                    Audit::log('bank.assigned', $payment, 'Kontoumsatz aufgeteilt: '.Money::format($r['amount']).' '.$text.' vom '.$tx->booking_date->format('d.m.Y').' an '.$lease->title());
                } else {
                    $entryId = null;
                    if ($r['kind'] === 'kaution') {
                        try {
                            $entry = Kaution::addEntry(Kaution::forLease($lease), $tx->booking_date->format('Y-m-d'), 'einzahlung',
                                (int) round((float) $r['amount'] * 100), $r['note'] !== '' ? $r['note'] : $from, $user);
                        } catch (\RuntimeException $e) {
                            throw new BankImportException('Kaution: '.$e->getMessage());
                        }
                        $entryId = $entry->id;
                    }
                    $item = BankTransactionItem::create([
                        'bank_transaction_id' => $tx->id,
                        'owner_id' => $tx->owner_id,
                        'lease_id' => $lease?->id,
                        'kind' => $r['kind'],
                        'amount' => $r['amount'],
                        'note' => $r['note'] !== '' ? $r['note'] : null,
                        'deposit_entry_id' => $entryId,
                        'created_by' => $user->id,
                    ]);
                    Audit::log('bank.item_added', $item, 'Kontoumsatz aufgeteilt: '.Money::format($r['amount']).' '.$text.' vom '.$tx->booking_date->format('d.m.Y').($lease ? ' an '.$lease->title() : '').($r['note'] !== '' ? ' ('.$r['note'].')' : ''));
                }

                if ($remember && ! $remembered && $lease && $tx->counterparty_iban_hash) {
                    LeasePayerAccount::query()->firstOrCreate(
                        ['lease_id' => $lease->id, 'iban_hash' => $tx->counterparty_iban_hash],
                        ['iban' => $tx->counterparty_iban, 'name' => $tx->counterparty_name, 'created_by' => $user->id]
                    );
                    $remembered = true;
                }
            }
            self::refreshStatus($tx->refresh());
        });

        foreach ($leases as $lease) {
            RentLedger::sync($lease);
        }

        return ['count' => count($clean), 'sum' => $sum];
    }

    /** Einen Teil ohne Mietzahlung (Kaution, Sonstiges) zurücknehmen; eine Kautionsbuchung wird mit entfernt. */
    public static function removeItem(BankTransaction $tx, BankTransactionItem $item, User $user): void
    {
        if ((int) $item->bank_transaction_id !== (int) $tx->id) {
            throw new BankImportException('Dieser Teil gehört nicht zu diesem Umsatz.');
        }
        DB::transaction(function () use ($tx, $item, $user) {
            self::dropDepositEntry($item);
            Audit::log('bank.item_removed', $item, 'Aufteilung entfernt: '.Money::format($item->amount).' '.$item->kindLabel().' vom '.$tx->booking_date->format('d.m.Y'), null, null, null, $user);
            $item->delete();
            self::refreshStatus($tx->refresh());
        });
    }

    /** @throws BankImportException */
    private static function dropDepositEntry(BankTransactionItem $item): void
    {
        if (! $item->deposit_entry_id) {
            return;
        }
        $entry = DepositEntry::query()->with('deposit')->find($item->deposit_entry_id);
        if (! $entry) {
            return;
        }
        if ($entry->deposit?->status === 'abgerechnet') {
            throw new BankImportException('Die Kaution ist schon abgerechnet. Bitte zuerst die Kautionsabrechnung zurücknehmen.');
        }
        $entry->delete();
    }

    public static function ignore(BankTransaction $tx, string $note): void
    {
        if ($tx->payments()->exists() || self::hasItems($tx)) {
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
            if (self::hasItems($tx)) {
                foreach ($tx->items()->get() as $item) {
                    self::dropDepositEntry($item);
                    $item->delete();
                }
            }
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

    /**
     * Angaben eines Umsatzes korrigieren (z. B. falsch eingelesen). Zuordnungen bleiben bestehen,
     * die Zahlungen übernehmen den neuen Buchungstag. Der Schlüssel gegen doppeltes Einlesen bleibt gleich.
     *
     * @param  array{booking_date: string, value_date: ?string, amount: string, counterparty_name: ?string, purpose: ?string, booking_text: ?string}  $data
     *
     * @throws BankImportException
     */
    public static function updateDetails(BankTransaction $tx, array $data, User $user): void
    {
        $amount = (float) $data['amount'];
        if (abs($amount) < 0.005) {
            throw new BankImportException('Der Betrag darf nicht 0 sein.');
        }
        $assigned = (float) $tx->payments()->sum('amount') + (self::hasItems($tx) ? (float) $tx->items()->sum('amount') : 0.0);
        $linked = (float) DB::table('bank_transaction_receipts')->where('bank_transaction_id', $tx->id)->sum('amount');
        $loan = \Illuminate\Support\Facades\Schema::hasTable('loan_events') && DB::table('loan_events')->where('bank_transaction_id', $tx->id)->exists();
        $amountChanged = number_format($amount, 2, '.', '') !== number_format((float) $tx->amount, 2, '.', '');
        $dateChanged = $data['booking_date'] !== $tx->booking_date->format('Y-m-d');

        if ($assigned > 0.004 && ($amount < 0 || $amount - $assigned < -0.004)) {
            throw new BankImportException('Davon sind schon '.Money::format($assigned).' Mietverträgen zugeordnet. Der Betrag muss mindestens so hoch sein, oder du nimmst zuerst Zuordnungen zurück.');
        }
        if ($linked > 0.004 && ($amount > 0 || abs($amount) - $linked < -0.004)) {
            throw new BankImportException('Davon sind schon '.Money::format($linked).' mit Belegen verknüpft. Der Betrag muss mindestens so hoch sein, oder du nimmst zuerst die Belegzuordnung zurück.');
        }
        if ($loan && ($amountChanged || $dateChanged)) {
            throw new BankImportException('Dieser Umsatz ist als Darlehensrate gebucht. Betrag und Datum bitte erst nach „Zurücksetzen“ bei den Ausgaben ändern.');
        }

        $old = $tx->only(['booking_date', 'value_date', 'amount', 'counterparty_name', 'purpose', 'booking_text']);
        $old['booking_date'] = $tx->booking_date->format('Y-m-d');
        $old['value_date'] = $tx->value_date?->format('Y-m-d');

        $leases = [];
        DB::transaction(function () use ($tx, $data, $amount, $dateChanged, $user, $old, &$leases) {
            $values = [
                'booking_date' => $data['booking_date'],
                'value_date' => $data['value_date'] ?: null,
                'amount' => number_format($amount, 2, '.', ''),
                'counterparty_name' => $data['counterparty_name'] !== null && trim($data['counterparty_name']) !== '' ? mb_substr(trim($data['counterparty_name']), 0, 190) : null,
                'purpose' => $data['purpose'] !== null && trim($data['purpose']) !== '' ? mb_substr(trim($data['purpose']), 0, 2000) : null,
                'booking_text' => $data['booking_text'] !== null && trim($data['booking_text']) !== '' ? mb_substr(trim($data['booking_text']), 0, 190) : null,
            ];
            // Vorzeichen gewechselt und noch nichts zugeordnet: Gutschrift ↔ Abbuchung
            if ($amount < 0 && in_array($tx->status, ['offen', 'teilweise', 'zugeordnet'], true) && ! $tx->payments()->exists() && ! self::hasItems($tx)) {
                $values['status'] = 'ausgabe';
                $values['status_note'] = null;
            } elseif ($amount > 0 && $tx->status === 'ausgabe') {
                $values['status'] = 'offen';
                $values['status_note'] = null;
            }
            $tx->update($values);

            if ($dateChanged) {
                foreach ($tx->payments()->get() as $p) {
                    $p->update(['paid_on' => $data['booking_date']]);
                    $leases[$p->lease_id] = true;
                }
            }
            self::refreshStatus($tx->refresh());

            $new = $values;
            unset($new['status'], $new['status_note']);
            Audit::log('bank.edited', $tx, 'Kontoumsatz bearbeitet: '.Money::format($tx->amount).' vom '.$tx->booking_date->format('d.m.Y'), $old, $new, null, $user);
        });

        foreach (array_keys($leases) as $id) {
            if ($lease = Lease::query()->find($id)) {
                RentLedger::sync($lease);
            }
        }
    }

    /**
     * Eine einzelne Zuordnung ändern: anderer Mietvertrag und/oder anderer Betrag.
     *
     * @throws BankImportException
     */
    public static function changePayment(BankTransaction $tx, RentPayment $payment, Lease $lease, string $amount, User $user): void
    {
        if ((int) $payment->bank_transaction_id !== (int) $tx->id) {
            throw new BankImportException('Diese Zahlung gehört nicht zu diesem Umsatz.');
        }
        if ((int) $lease->owner_id !== (int) $tx->owner_id) {
            throw new BankImportException('Der Mietvertrag gehört zu einem anderen Eigentümer als das Konto.');
        }
        $value = (float) $amount;
        $max = round((float) $tx->amount - (float) $tx->payments()->where('id', '!=', $payment->id)->sum('amount') - (self::hasItems($tx) ? (float) $tx->items()->sum('amount') : 0.0), 2);
        if ($value <= 0 || $value - $max > 0.004) {
            throw new BankImportException('Der Betrag muss größer als 0 und höchstens '.Money::format($max).' sein.');
        }
        $oldLease = $payment->lease_id;
        $lease->loadMissing(['unit.property', 'tenant']);

        DB::transaction(function () use ($tx, $payment, $lease, $amount, $user) {
            $before = Money::format($payment->amount).' an '.($payment->lease?->title() ?? 'Vertrag '.$payment->lease_id);
            $payment->update(['lease_id' => $lease->id, 'owner_id' => $lease->owner_id, 'amount' => $amount]);
            self::refreshStatus($tx);
            Audit::log('bank.payment_changed', $payment, 'Zuordnung geändert: vorher '.$before.', jetzt '.Money::format($amount).' an '.$lease->title(), null, null, null, $user);
        });

        foreach (array_unique([(int) $oldLease, (int) $lease->id]) as $id) {
            if ($l = Lease::query()->find($id)) {
                RentLedger::sync($l);
            }
        }
    }

    /** Eine einzelne Zuordnung zurücknehmen (die Zahlung wird storniert, der Rest bleibt). */
    public static function removePayment(BankTransaction $tx, RentPayment $payment, User $user): void
    {
        if ((int) $payment->bank_transaction_id !== (int) $tx->id) {
            throw new BankImportException('Diese Zahlung gehört nicht zu diesem Umsatz.');
        }
        $lease = Lease::query()->find($payment->lease_id);
        DB::transaction(function () use ($tx, $payment, $user, $lease) {
            Audit::log('bank.payment_removed', $payment, 'Zuordnung entfernt: '.Money::format($payment->amount).' vom '.$tx->booking_date->format('d.m.Y').($lease ? ' an '.$lease->title() : ''), null, null, null, $user);
            $payment->delete();
            self::refreshStatus($tx);
        });
        if ($lease) {
            RentLedger::sync($lease);
        }
    }

    /** Status nach den zugeordneten Zahlungen setzen (nach Zuordnung oder Storno einer Zahlung). */
    public static function refreshStatus(BankTransaction $tx): void
    {
        if ((float) $tx->amount < 0 || $tx->status === 'ignoriert') {
            return;
        }
        $assigned = $tx->assignedTotal();
        $status = $assigned <= 0.004 ? 'offen' : ((float) $tx->amount - $assigned > 0.004 ? 'teilweise' : 'zugeordnet');
        if ($status !== $tx->status) {
            $tx->update(['status' => $status]);
        }
    }

    /** Gibt es aufgeteilte Teile ohne Mietzahlung? (Tabelle fehlt vor der Migration.) */
    public static function hasItems(BankTransaction $tx): bool
    {
        try {
            return $tx->items()->exists();
        } catch (Throwable) {
            return false;
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
