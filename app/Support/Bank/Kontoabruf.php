<?php

namespace App\Support\Bank;

use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use App\Support\Settings;
use App\Support\Stammdaten;
use DateTimeImmutable;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\StatementOfAccount\StatementOfAccount;
use Fhp\Model\StatementOfAccount\Transaction;
use Fhp\Options\Credentials;
use Fhp\Options\FinTsOptions;
use Throwable;

/**
 * Automatischer Kontoabruf per FinTS (Paket 47), z. B. für die Sparkasse mit S-pushTAN.
 *
 * Ablauf: anmelden → Konten → Umsätze ab dem letzten Abruf (beim ersten Mal 90 Tage) → wie beim CSV-Import anlegen.
 * Verlangt die Bank eine Freigabe (TAN / pushTAN), wird der Stand gespeichert und der Abruf wartet,
 * bis die Freigabe im CRM bestätigt ist ({@see weiter()}). Der tägliche Lauf fragt nie selbst nach einer TAN.
 *
 * Voraussetzung: FinTS-Produktregistrierung bei der Deutschen Kreditwirtschaft (Einstellung fints_product_id).
 */
class Kontoabruf
{
    public const FIRST_DAYS = 90;

    public const OVERLAP_DAYS = 5;

    public static function productId(): string
    {
        return trim((string) Settings::get('fints_product_id', ''));
    }

    /** Bankleitzahl aus einer deutschen IBAN. */
    public static function blzAusIban(?string $iban): string
    {
        $i = Stammdaten::normalizeIban((string) $iban);

        return str_starts_with($i, 'DE') && strlen($i) === 22 ? substr($i, 4, 8) : '';
    }

    private static function fints(BankConnection $c, ?string $persisted = null): FinTs
    {
        if (self::productId() === '') {
            throw new \RuntimeException('Die FinTS-Registrierungsnummer fehlt. Bitte unter Umsätze → Kontoabruf eintragen.');
        }
        $o = new FinTsOptions;
        $o->url = trim($c->url);
        $o->bankCode = trim($c->bank_code);
        $o->productName = self::productId();
        $o->productVersion = '1.0';
        $o->timeoutConnect = 15;
        $o->timeoutResponse = 60;

        return FinTs::new($o, Credentials::create((string) $c->username, (string) $c->pin), $persisted);
    }

    private static function state(BankConnection $c): ?string
    {
        $s = (string) ($c->state ?? '');

        return $s !== '' ? (base64_decode($s, true) ?: null) : null;
    }

    /**
     * TAN-Verfahren der Bank (ohne Anmeldung abrufbar).
     *
     * @return array<int, array{id: int, name: string, medium: bool, decoupled: bool}>
     */
    public static function tanVerfahren(BankConnection $c): array
    {
        $f = self::fints($c);
        $out = [];
        foreach ($f->getTanModes() as $m) {
            $out[] = ['id' => $m->getId(), 'name' => $m->getName(), 'medium' => $m->needsTanMedium(), 'decoupled' => $m->isDecoupled()];
        }
        $c->forceFill(['state' => base64_encode($f->persist())])->save();

        return $out;
    }

    /** Namen der TAN-Medien (z. B. das Handy mit der S-pushTAN-App) für ein Verfahren. */
    public static function tanMedien(BankConnection $c, int $mode): array
    {
        $f = self::fints($c, self::state($c));
        $names = array_map(fn ($m) => $m->getName(), $f->getTanMedia($mode));
        $c->forceFill(['state' => base64_encode($f->persist())])->save();

        return $names;
    }

    /**
     * Startet einen Abruf. Rückgabe: ['status' => 'fertig'|'tan', 'text' => …, 'tan' => [...]?]
     */
    public static function start(BankConnection $c, ?User $user = null): array
    {
        try {
            $f = self::fints($c, self::state($c));
            if ($c->tan_mode !== null && $c->tan_mode !== '') {
                $f->selectTanMode((int) $c->tan_mode, $c->tan_medium ?: null);
            }
            $login = $f->login();
            if ($login->needsTan()) {
                return self::warte($c, $f, $login, 'login', []);
            }

            return self::konto($c, $f, $user);
        } catch (Throwable $e) {
            return self::fehler($c, $e);
        }
    }

    /**
     * Setzt einen Abruf nach der Freigabe fort: mit TAN (Eingabe) oder nach Bestätigung in der App (decoupled).
     */
    public static function weiter(BankConnection $c, ?string $tan = null, ?User $user = null): array
    {
        try {
            $p = self::pending($c);
            if ($p === null) {
                return ['status' => 'fehler', 'text' => 'Es wartet kein Abruf auf eine Freigabe. Bitte neu starten.'];
            }
            $f = self::fints($c, $p['fints']);
            $action = unserialize($p['action']);
            if (! $action instanceof BaseAction) {
                throw new \RuntimeException('Der gespeicherte Abruf ist unvollständig. Bitte neu starten.');
            }
            if ($tan !== null && $tan !== '') {
                $f->submitTan($action, $tan);
            } elseif (! $f->checkDecoupledSubmission($action)) {
                $c->forceFill(['pending' => base64_encode(serialize(['fints' => $f->persist(), 'action' => serialize($action)] + $p))])->save();

                return ['status' => 'tan', 'text' => 'Die Freigabe ist bei der Bank noch nicht angekommen. Bitte in der App bestätigen und dann noch einmal auf „Freigegeben – weiter“ tippen.', 'tan' => $p['tan'] ?? []];
            }
            $c->forceFill(['pending' => null])->save();

            if ($p['step'] === 'umsaetze' && $action instanceof GetStatementOfAccount) {
                return self::anlegen($c, $f, $action->getStatement(), $p['from'], $user);
            }

            return self::konto($c, $f, $user);
        } catch (Throwable $e) {
            return self::fehler($c, $e);
        }
    }

    private static function pending(BankConnection $c): ?array
    {
        $raw = (string) ($c->pending ?? '');
        if ($raw === '') {
            return null;
        }
        $p = unserialize((string) base64_decode($raw, true), ['allowed_classes' => false]);

        return is_array($p) ? $p : null;
    }

    private static function konto(BankConnection $c, FinTs $f, ?User $user): array
    {
        $iban = Stammdaten::normalizeIban((string) $c->account?->iban);
        $get = GetSEPAAccounts::create();
        $f->execute($get);
        if ($get->needsTan()) {
            return self::warte($c, $f, $get, 'konten', []);
        }
        $acc = null;
        foreach ($get->getAccounts() as $a) {
            if (Stammdaten::normalizeIban((string) $a->getIban()) === $iban) {
                $acc = $a;
            }
        }
        if ($acc === null) {
            throw new \RuntimeException('Das Konto '.trim(chunk_split($iban, 4, ' ')).' ist in diesem Online-Banking-Zugang nicht zu finden.');
        }

        $from = $c->last_to
            ? (new DateTimeImmutable($c->last_to->format('Y-m-d')))->modify('-'.self::OVERLAP_DAYS.' days')
            : new DateTimeImmutable('-'.self::FIRST_DAYS.' days');
        $get = GetStatementOfAccount::create($acc, new \DateTime($from->format('Y-m-d')), new \DateTime('today'), false, false);
        $f->execute($get);
        if ($get->needsTan()) {
            return self::warte($c, $f, $get, 'umsaetze', ['from' => $from->format('Y-m-d')]);
        }

        return self::anlegen($c, $f, $get->getStatement(), $from->format('Y-m-d'), $user);
    }

    private static function warte(BankConnection $c, FinTs $f, BaseAction $action, string $step, array $extra): array
    {
        $req = $action->getTanRequest();
        $mode = $f->getSelectedTanMode();
        $tan = [
            'text' => $req?->getChallenge(),
            'medium' => $req?->getTanMediumName(),
            'decoupled' => $mode?->isDecoupled() ?? false,
        ];
        $c->forceFill([
            'pending' => base64_encode(serialize(['step' => $step, 'fints' => $f->persist(), 'action' => serialize($action), 'tan' => $tan] + $extra + ['from' => null])),
            'status' => 'tan',
            'last_message' => 'Freigabe nötig ('.date('d.m.Y H:i').')',
        ])->save();

        return ['status' => 'tan', 'text' => $tan['decoupled'] ? 'Bitte den Abruf in der S-pushTAN-App freigeben und danach auf „Freigegeben – weiter“ tippen.' : 'Bitte die TAN eingeben.', 'tan' => $tan];
    }

    private static function anlegen(BankConnection $c, FinTs $f, StatementOfAccount $soa, ?string $from, ?User $user): array
    {
        $account = $c->account;
        $rows = self::zeilen($soa);
        [$rows, $dupes] = self::ohneVorhandene($rows, (int) $account->id);
        $new = 0;
        if ($rows !== []) {
            $dates = array_column($rows, 'booking_date');
            $import = BankService::importParsed([
                'format' => 'FinTS',
                'account_iban' => Stammdaten::normalizeIban((string) $account->iban),
                'rows' => BankCsvParser::withDedupeKeys($rows),
                'skipped' => 0,
                'from' => min($dates),
                'to' => max($dates),
            ], 'Kontoabruf '.date('d.m.Y H:i'), $account, $user);
            $new = (int) $import->rows_new;
        }
        try {
            $f->close();
        } catch (Throwable) {
        }
        $text = $new === 0 ? 'Keine neuen Umsätze.' : $new.' neue Umsätze abgerufen.';
        $c->forceFill([
            'state' => base64_encode($f->persist()),
            'pending' => null,
            'status' => 'bereit',
            'last_fetch_at' => now(),
            'last_to' => date('Y-m-d'),
            'last_error' => null,
            'last_message' => $text.($dupes > 0 ? ' '.$dupes.' waren schon vorhanden.' : ''),
        ])->save();

        return ['status' => 'fertig', 'text' => $text, 'neu' => $new];
    }

    private static function fehler(BankConnection $c, Throwable $e): array
    {
        // Keine Zugangsdaten in die Meldung: die Bibliothek nennt sie nicht, zur Sicherheit trotzdem kürzen
        $msg = mb_substr(trim($e->getMessage()), 0, 500) ?: get_class($e);
        $c->forceFill(['status' => 'fehler', 'last_error' => $msg, 'pending' => null])->save();

        return ['status' => 'fehler', 'text' => $msg];
    }

    /**
     * Gebuchte Umsätze im Format des CSV-Parsers (ohne Dedupe-Schlüssel).
     *
     * @return array<int, array>
     */
    public static function zeilen(StatementOfAccount $soa): array
    {
        $rows = [];
        foreach ($soa->getStatements() as $st) {
            foreach ($st->getTransactions() as $t) {
                if (! $t->getBooked()) {
                    continue;
                }
                $rows[] = self::zeile($t);
            }
        }

        return $rows;
    }

    public static function zeile(Transaction $t): array
    {
        $s = $t->getStructuredDescription();
        $purpose = trim((string) ($t->getMainDescription() ?: trim($t->getDescription1().' '.$t->getDescription2())));
        $amount = number_format(abs($t->getAmount()), 2, '.', '');
        if ($t->getCreditDebit() === Transaction::CD_DEBIT) {
            $amount = '-'.$amount;
        }
        $iban = Stammdaten::normalizeIban((string) $t->getAccountNumber());
        $eref = trim((string) $t->getEndToEndID());
        $booking = $t->getBookingDate() ?? $t->getValutaDate() ?? $t->getDate();

        return [
            'account' => null,
            'booking_date' => $booking?->format('Y-m-d'),
            'value_date' => $t->getValutaDate()?->format('Y-m-d'),
            'amount' => $amount,
            'currency' => 'EUR',
            'booking_text' => mb_substr(trim($t->getBookingText()), 0, 100) ?: null,
            'purpose' => $purpose !== '' ? $purpose : null,
            'name' => mb_substr(trim($t->getName()), 0, 190) ?: null,
            'iban' => preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban) ? $iban : null,
            'bic' => mb_substr(strtoupper(trim($t->getBankCode())), 0, 11) ?: null,
            'end_to_end' => $eref !== '' ? mb_substr($eref, 0, 100) : null,
            'mandate_ref' => isset($s['MREF']) ? mb_substr(trim($s['MREF']), 0, 100) : null,
            'creditor_id' => isset($s['CRED']) ? mb_substr(trim($s['CRED']), 0, 40) : null,
        ];
    }

    /**
     * Entfernt Umsätze, die es schon gibt (z. B. aus einer früher eingelesenen CSV-Datei): gleiches Konto,
     * Buchungstag, Betrag und Gegenkonto. Gleiche Umsätze am selben Tag werden gezählt.
     *
     * @return array{0: array<int, array>, 1: int}
     */
    public static function ohneVorhandene(array $rows, int $accountId): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => $r['booking_date'] !== null));
        if ($rows === []) {
            return [[], 0];
        }
        $dates = array_column($rows, 'booking_date');
        $have = [];
        foreach (BankTransaction::query()->where('owner_bank_account_id', $accountId)->whereBetween('booking_date', [min($dates), max($dates)])->get(['booking_date', 'amount', 'counterparty_iban_hash']) as $tx) {
            $k = $tx->booking_date->format('Y-m-d').'|'.number_format((float) $tx->amount, 2, '.', '').'|'.($tx->counterparty_iban_hash ?? '');
            $have[$k] = ($have[$k] ?? 0) + 1;
        }

        return self::filterVorhandene($rows, $have);
    }

    /** Reine Logik zu {@see ohneVorhandene()}: $have = Schlüssel „Tag|Betrag|IBAN-Hash“ => Anzahl. */
    public static function filterVorhandene(array $rows, array $have): array
    {
        $out = [];
        $dupes = 0;
        foreach ($rows as $r) {
            $k = $r['booking_date'].'|'.number_format((float) $r['amount'], 2, '.', '').'|'.(BankMatcher::ibanHash($r['iban']) ?? '');
            if (($have[$k] ?? 0) > 0) {
                $have[$k]--;
                $dupes++;

                continue;
            }
            $out[] = $r;
        }

        return [$out, $dupes];
    }
}
