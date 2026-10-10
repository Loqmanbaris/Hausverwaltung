<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Inquiry;
use App\Models\SentEmail;
use App\Support\Audit;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Mail\Outbox;
use Illuminate\Http\Request;
use App\Support\Mail\MailError;

/** E-Mail-Versand aus dem CRM (Paket 21): Schreiben vorbereiten, PDF ansehen, senden, Verlauf. */
class MailController extends Controller
{
    use ScopesOwners;

    private function resolve(Request $request): array
    {
        $kind = (string) $request->input('art');
        abort_unless(in_array($kind, Outbox::KINDS, true), 404);
        $ids = (string) $request->input('ids');
        if (in_array($kind, ['nk', 'verbrauch'], true) && $request->filled('vertrag')) {
            $ids = (int) $ids.','.(int) $request->input('vertrag');
        }

        return Outbox::resolve($kind, $ids, $this->me());
    }

    public function compose(Request $request)
    {
        try {
            $box = $this->resolve($request);
        } catch (MailError $e) {
            return back()->withErrors(['mail' => $e->getMessage()]);
        }
        $single = count($box['items']) === 1;

        return view('mail.compose', [
            'box' => $box,
            'single' => $single,
            'subject' => $single ? Outbox::fill($box['subject'], $box['items'][0]['vars']) : $box['subject'],
            'body' => $single ? Outbox::fill($box['body'], $box['items'][0]['vars']) : $box['body'],
            'configured' => MailSettings::configured(),
            'copy' => MailSettings::copyAddress(),
            'query' => ['art' => $request->input('art'), 'ids' => $request->input('ids'), 'vertrag' => $request->input('vertrag')],
        ]);
    }

    /** PDF eines Empfängers ansehen (gleiche Datei wie im Anhang). */
    public function preview(Request $request)
    {
        try {
            $box = $this->resolve($request);
        } catch (MailError $e) {
            abort(404);
        }
        $item = collect($box['items'])->firstWhere('key', (string) $request->query('key')) ?? $box['items'][0];
        abort_if($item['pdf'] === null, 404);

        return response(($item['pdf'])(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$item['pdf_name'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Schreiben als PDF herunterladen (Mietvertrag, Übergabeprotokoll, Ratenvereinbarung, Mahnung). */
    public function document(Request $request)
    {
        $kind = (string) $request->query('art');
        abort_unless(in_array($kind, ['vertrag', 'uebergabe', 'raten', 'mahnung'], true), 404);
        try {
            $box = Outbox::resolve($kind, (string) (int) $request->query('id'), $this->me());
        } catch (MailError) {
            abort(404);
        }
        $item = $box['items'][0];
        abort_if($item['pdf'] === null, 404);

        return response(($item['pdf'])(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$item['pdf_name'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'art' => ['required', 'string'],
            'ids' => ['required', 'string', 'max:2000'],
            'vertrag' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['string', 'max:20'],
            'to' => ['nullable', 'array'],
            'to.*' => ['nullable', 'string', 'max:500'],
            'datei' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'subject.required' => 'Bitte einen Betreff eingeben.',
            'body.required' => 'Bitte einen Text eingeben.',
            'items.required' => 'Bitte mindestens einen Empfänger auswählen.',
            'datei.max' => 'Der Anhang darf höchstens 10 MB groß sein.',
            'datei.mimes' => 'Als Anhang gehen PDF, JPG oder PNG.',
        ]);
        try {
            $box = $this->resolve($request);
        } catch (MailError $e) {
            return back()->withInput()->withErrors(['mail' => $e->getMessage()]);
        }
        if (! MailSettings::configured() && MailService::$fake === null) {
            return back()->withInput()->withErrors(['mail' => 'Der E-Mail-Versand ist noch nicht eingerichtet. Ein Administrator trägt die Zugangsdaten unter Verwaltung → E-Mail ein.']);
        }

        $upload = null;
        if ($request->hasFile('datei') && $box['upload']) {
            $f = $request->file('datei');
            $upload = ['name' => mb_substr(preg_replace('/[^\pL\pN._ -]/u', '_', $f->getClientOriginalName()), 0, 100), 'data' => (string) file_get_contents($f->getRealPath()), 'mime' => $f->getMimeType() ?: 'application/octet-stream'];
        }

        $chosen = array_flip($data['items']);
        $ok = 0;
        $errors = [];
        foreach ($box['items'] as $item) {
            if (! isset($chosen[$item['key']])) {
                continue;
            }
            $raw = (string) ($data['to'][$item['key']] ?? implode(', ', $item['addresses']));
            $to = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $raw) ?: [])));
            $attachments = [];
            try {
                if ($item['pdf']) {
                    $attachments[] = ['name' => $item['pdf_name'], 'data' => ($item['pdf'])(), 'mime' => 'application/pdf'];
                }
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $item['label'].': PDF konnte nicht erstellt werden.';

                continue;
            }
            if ($upload) {
                $attachments[] = $upload;
            }
            $log = MailService::send($to, Outbox::fill($data['subject'], $item['vars']), Outbox::fill($data['body'], $item['vars']), $attachments, [
                'kind' => $box['kind'], 'ref_id' => $item['ref_id'] ?? null, 'owner_id' => $item['owner_id'], 'tenant_id' => $item['tenant_id'], 'lease_id' => $item['lease_id'],
            ], $this->me());
            if ($log->status === 'gesendet') {
                $ok++;
                // Gesendetes Schreiben automatisch ablegen (Paket 22)
                $cat = ['mahnung' => 'mahnung', 'nk' => 'abrechnung', 'vertrag' => 'mietvertrag', 'uebergabe' => 'uebergabe', 'raten' => 'raten'][$box['kind']] ?? null;
                if ($cat && $item['pdf'] && class_exists(\App\Support\Ablage\Ablage::class) && \Illuminate\Support\Facades\Schema::hasTable('documents')) {
                    [$aType, $aId] = $item['lease_id'] ? ['lease', (int) $item['lease_id']] : ['tenant', (int) $item['tenant_id']];
                    if ($aId > 0) {
                        \App\Support\Ablage\Ablage::storeGenerated($aType, $aId, $cat, $log->subject, $attachments[0]['data'], $item['pdf_name'], $this->me());
                    }
                }
                Audit::log('mail.sent', $log, $log->kindLabel().' an '.$log->recipients.': '.$log->subject);
                if ($box['kind'] === 'anfrage') {
                    Inquiry::query()->whereKey($item['ref_id'])->where('status', 'neu')->update(['status' => 'in_bearbeitung']);
                }
            } else {
                $errors[] = $item['label'].': '.$log->error;
                Audit::log('mail.failed', $log, $log->kindLabel().' an '.($log->recipients ?: '–').': '.$log->error);
            }
        }

        // Verbrauchsinformation als versendet vermerken (Paket 26)
        if ($box['kind'] === 'verbrauch' && $ok > 0) {
            \App\Models\ConsumptionNotice::create(['property_id' => $box['property_id'], 'month' => $box['month'].'-01', 'method' => 'email', 'count' => $ok, 'created_by' => $this->me()->id]);
        }

        $msg = $ok === 0 ? 'Es wurde keine E-Mail gesendet.' : ($ok === 1 ? 'Die E-Mail wurde gesendet.' : $ok.' E-Mails wurden gesendet.');
        $redirect = redirect()->to($box['back'])->with('status', $msg);
        if ($errors !== []) {
            return $ok === 0
                ? back()->withInput()->withErrors(['mail' => implode(' ', $errors)])
                : $redirect->withErrors(['mail' => 'Nicht gesendet: '.implode(' ', $errors)]);
        }

        return $redirect;
    }

    public function index(Request $request)
    {
        $kind = (string) $request->query('art');
        $q = trim((string) $request->query('q'));
        $mails = SentEmail::visibleTo($this->me())
            ->when(isset(SentEmail::KINDS[$kind]), fn ($w) => $w->where('kind', $kind))
            ->when($request->query('status') === 'fehler', fn ($w) => $w->where('status', 'fehler'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('recipients', 'like', '%'.$q.'%')->orWhere('subject', 'like', '%'.$q.'%')))
            ->with(['user', 'tenant'])
            ->orderByDesc('id')
            ->simplePaginate(50)->withQueryString();

        return view('mail.index', ['mails' => $mails, 'kind' => $kind, 'q' => $q, 'configured' => MailSettings::configured()]);
    }
}
