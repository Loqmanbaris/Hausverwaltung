<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\BankConnection;
use App\Models\OwnerBankAccount;
use App\Support\Audit;
use App\Support\Bank\Kontoabruf;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Automatischer Kontoabruf per FinTS einrichten und ausführen (Paket 48, nur Administrator). */
class KontoabrufController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $accounts = OwnerBankAccount::query()->whereIn('owner_id', $this->visibleOwnerIds())->with('owner')->get()
            ->sortBy(fn ($a) => [$a->owner?->name, $a->label])->values();
        $conns = BankConnection::query()->whereIn('owner_bank_account_id', $accounts->pluck('id'))->get()->keyBy('owner_bank_account_id');

        return view('bank.kontoabruf', [
            'accounts' => $accounts,
            'conns' => $conns,
            'productId' => Kontoabruf::productId(),
        ]);
    }

    public function saveProduct(Request $request)
    {
        $data = $request->validate(['fints_product_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]*$/']], ['fints_product_id.*' => 'Die Registrierungsnummer besteht nur aus Buchstaben und Ziffern.']);
        Settings::putMany(['fints_product_id' => trim((string) ($data['fints_product_id'] ?? ''))]);
        Audit::log('fints.product', null, 'FinTS-Registrierungsnummer geändert');

        return redirect()->route('bank.abruf')->with('status', 'Registrierungsnummer gespeichert.');
    }

    private function account(int $id): OwnerBankAccount
    {
        $a = OwnerBankAccount::query()->findOrFail($id);
        $this->assertOwnerAccess((int) $a->owner_id);

        return $a;
    }

    private function conn(int $id): BankConnection
    {
        $c = BankConnection::query()->with('account')->findOrFail($id);
        $this->assertOwnerAccess((int) $c->account?->owner_id);

        return $c;
    }

    public function save(Request $request, int $account)
    {
        $a = $this->account($account);
        $c = BankConnection::query()->firstWhere('owner_bank_account_id', $a->id);
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:255'],
            'bank_code' => ['required', 'digits:8'],
            'username' => [$c ? 'nullable' : 'required', 'string', 'max:100'],
            'pin' => [$c ? 'nullable' : 'required', 'string', 'max:100'],
        ], [
            'url.*' => 'Bitte die FinTS-Adresse (PIN/TAN, beginnt mit https://) angeben.',
            'bank_code.*' => 'Die Bankleitzahl hat 8 Ziffern.',
            'username.*' => 'Bitte den Anmeldenamen bzw. die Legitimations-ID des Online-Bankings angeben.',
            'pin.*' => 'Bitte die Online-Banking-PIN angeben.',
        ]);
        $values = ['url' => trim($data['url']), 'bank_code' => $data['bank_code']];
        if (! empty($data['username'])) {
            $values['username'] = trim($data['username']);
        }
        if (! empty($data['pin'])) {
            $values['pin'] = $data['pin'];
        }
        if ($c === null) {
            $c = BankConnection::create($values + ['owner_bank_account_id' => $a->id, 'status' => 'neu']);
        } else {
            $c->fill($values);
            // Neue Zugangsdaten oder Adresse: alte Sitzung verwerfen
            if ($c->isDirty(['url', 'bank_code', 'username', 'pin'])) {
                $c->state = null;
                $c->pending = null;
                $c->status = 'neu';
            }
            $c->save();
        }
        Audit::log('fints.saved', $c, 'Kontoabruf eingerichtet/geändert für Konto '.$a->formattedIban());

        return redirect()->to(route('bank.abruf').'#konto-'.$a->id)->with('status', 'Gespeichert. Als Nächstes das TAN-Verfahren wählen.');
    }

    public function tanModes(int $conn)
    {
        $c = $this->conn($conn);
        try {
            $modes = Kontoabruf::tanVerfahren($c);
        } catch (\Throwable $e) {
            return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)->withErrors(['fints' => 'Die Bank hat nicht geantwortet: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)->with('fints_modes_'.$c->id, $modes);
    }

    public function tanSelect(Request $request, int $conn)
    {
        $c = $this->conn($conn);
        $data = $request->validate(['tan_mode' => ['required', 'integer'], 'tan_medium' => ['nullable', 'string', 'max:100'], 'needs_medium' => ['nullable', 'boolean']]);
        if (($data['needs_medium'] ?? false) && empty($data['tan_medium'])) {
            try {
                $media = Kontoabruf::tanMedien($c, (int) $data['tan_mode']);
            } catch (\Throwable $e) {
                return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)->withErrors(['fints' => 'TAN-Medien konnten nicht geladen werden: '.mb_substr($e->getMessage(), 0, 300)]);
            }
            if (count($media) > 1) {
                return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)
                    ->with('fints_media_'.$c->id, ['mode' => (int) $data['tan_mode'], 'media' => $media]);
            }
            $data['tan_medium'] = $media[0] ?? null;
        }
        $c->forceFill(['tan_mode' => (string) $data['tan_mode'], 'tan_medium' => $data['tan_medium'] ?: null])->save();
        Audit::log('fints.tan', $c, 'TAN-Verfahren für den Kontoabruf gewählt');

        return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)->with('status', 'TAN-Verfahren gespeichert. Jetzt „Jetzt abrufen“ tippen.');
    }

    public function run(int $conn)
    {
        $c = $this->conn($conn);
        @set_time_limit(180);
        $r = Kontoabruf::start($c, $this->me());

        return $this->result($c, $r);
    }

    public function weiter(Request $request, int $conn)
    {
        $c = $this->conn($conn);
        $data = $request->validate(['tan' => ['nullable', 'string', 'max:40']]);
        @set_time_limit(180);
        $r = Kontoabruf::weiter($c, trim((string) ($data['tan'] ?? '')) ?: null, $this->me());

        return $this->result($c, $r);
    }

    private function result(BankConnection $c, array $r)
    {
        $to = redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id);
        if ($r['status'] === 'fehler') {
            return $to->withErrors(['fints' => 'Abruf fehlgeschlagen: '.$r['text']]);
        }

        return $to->with('status', $r['text']);
    }

    public function toggle(int $conn)
    {
        $c = $this->conn($conn);
        $c->forceFill(['active' => ! $c->active])->save();
        Audit::log('fints.toggle', $c, 'Täglicher Kontoabruf '.($c->active ? 'eingeschaltet' : 'ausgeschaltet'));

        return redirect()->to(route('bank.abruf').'#konto-'.$c->owner_bank_account_id)->with('status', $c->active ? 'Täglicher Abruf ist an.' : 'Täglicher Abruf ist aus.');
    }

    public function destroy(int $conn)
    {
        $c = $this->conn($conn);
        $id = $c->owner_bank_account_id;
        $c->delete();
        Audit::log('fints.deleted', null, 'Kontoabruf gelöscht (Zugangsdaten entfernt)');

        return redirect()->to(route('bank.abruf').'#konto-'.$id)->with('status', 'Kontoabruf gelöscht, die Zugangsdaten sind entfernt.');
    }
}
