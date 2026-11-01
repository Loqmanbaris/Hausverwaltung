<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\ReceivedEmail;
use App\Support\Audit;
use App\Support\Mail\Inbox;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Posteingang: empfangene E-Mails lesen, beantworten, weiterleiten; freie E-Mails schreiben. */
class InboxController extends Controller
{
    use ScopesOwners;

    private const UPLOAD_RULES = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,heic,doc,docx,xls,xlsx,odt,ods,txt,csv,zip'];

    private function find(int $id): ReceivedEmail
    {
        return ReceivedEmail::visibleTo($this->me())->findOrFail($id);
    }

    public function index(Request $request)
    {
        $view = in_array($request->query('ansicht'), ['ungelesen', 'archiv'], true) ? $request->query('ansicht') : 'eingang';
        $q = trim((string) $request->query('q'));
        $mails = ReceivedEmail::visibleTo($this->me())
            ->where('archived', $view === 'archiv')
            ->when($view === 'ungelesen', fn ($w) => $w->whereNull('read_at'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('from_address', 'like', '%'.$q.'%')->orWhere('from_name', 'like', '%'.$q.'%')
                ->orWhere('subject', 'like', '%'.$q.'%')->orWhere('body', 'like', '%'.$q.'%')))
            ->when($request->filled('mieter'), fn ($w) => $w->where('tenant_id', (int) $request->query('mieter')))
            ->with(['tenant', 'vendor', 'owner'])
            ->orderByDesc('received_at')->orderByDesc('id')
            ->simplePaginate(50)->withQueryString();

        return view('mail.inbox', [
            'mails' => $mails,
            'view' => $view,
            'q' => $q,
            'receive' => MailSettings::receiveConfigured(),
            'send' => MailSettings::configured(),
            'lastFetch' => Inbox::lastFetch(),
            'lastError' => Inbox::lastError(),
            'unread' => ReceivedEmail::unreadCountFor($this->me()),
        ]);
    }

    public function fetch()
    {
        $r = Inbox::fetch();
        if ($r['fehler']) {
            return back()->withErrors(['mail' => 'Abruf fehlgeschlagen: '.$r['fehler']]);
        }
        $msg = $r['neu'] === 0 ? 'Keine neuen E-Mails.' : ($r['neu'] === 1 ? '1 neue E-Mail.' : $r['neu'].' neue E-Mails.');
        if ($r['rest'] > 0) {
            $msg .= ' Weitere '.$r['rest'].' folgen beim nächsten Abruf.';
        }

        return back()->with('status', $msg);
    }

    public function show(int $id)
    {
        $mail = $this->find($id);
        if ($mail->read_at === null) {
            $mail->forceFill(['read_at' => now(), 'read_by' => $this->me()->id])->save();
        }

        return view('mail.inbox-show', [
            'mail' => $mail->load(['tenant', 'vendor', 'owner', 'applicant']),
            'send' => MailSettings::configured(),
            'replyBody' => $this->closing()."\n\n".$this->quote($mail),
            'replySubject' => $this->prefixed('AW', $mail->subject),
        ]);
    }

    public function attachment(int $id, int $n)
    {
        $mail = $this->find($id);
        $a = ($mail->attachments ?? [])[$n] ?? null;
        abort_if($a === null || ! Storage::disk('local')->exists($a['path']), 404);
        $safe = in_array($a['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'], true);
        $name = str_replace(['"', '\\'], '', $a['name']);

        return response(Storage::disk('local')->get($a['path']), 200, [
            'Content-Type' => $safe ? $a['mime'] : 'application/octet-stream',
            'Content-Disposition' => ($safe ? 'inline' : 'attachment').'; filename="'.preg_replace('/[^\x20-\x7E]/', '_', $name).'"; filename*=UTF-8\'\''.rawurlencode($a['name']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function archive(int $id)
    {
        $mail = $this->find($id);
        $mail->forceFill(['archived' => ! $mail->archived])->save();

        return $mail->archived
            ? redirect()->route('inbox.index')->with('status', 'Ins Archiv verschoben.')
            : back()->with('status', 'Wieder im Posteingang.');
    }

    public function unread(int $id)
    {
        $this->find($id)->forceFill(['read_at' => null, 'read_by' => null])->save();

        return redirect()->route('inbox.index')->with('status', 'Als ungelesen markiert.');
    }

    public function reply(Request $request, int $id)
    {
        $mail = $this->find($id);
        $data = $this->validateMail($request);
        $to = $this->recipients($data['to']);
        $log = MailService::send($to, $data['subject'], $data['body'], $this->uploads($request), [
            'kind' => 'antwort', 'ref_id' => $mail->id, 'owner_id' => $mail->owner_id, 'tenant_id' => $mail->tenant_id,
        ], $this->me(), true, $this->threadFor($mail));

        if ($log->status !== 'gesendet') {
            Audit::log('mail.failed', $log, 'Antwort an '.($log->recipients ?: '–').': '.$log->error);

            return back()->withInput()->withErrors(['mail' => 'Nicht gesendet: '.$log->error]);
        }
        $mail->forceFill(['replied_at' => now()])->save();
        Audit::log('mail.sent', $log, 'Antwort an '.$log->recipients.': '.$log->subject);

        return redirect()->route('inbox.show', $mail->id)->with('status', 'Die Antwort wurde gesendet.');
    }

    /** Neue E-Mail schreiben, auch zum Weiterleiten einer empfangenen E-Mail. */
    public function write(Request $request)
    {
        $fwd = $request->filled('weiter') ? $this->find((int) $request->query('weiter')) : null;

        return view('mail.write', [
            'send' => MailSettings::configured(),
            'fwd' => $fwd,
            'to' => (string) $request->query('an', ''),
            'subject' => $fwd ? $this->prefixed('WG', $fwd->subject) : (string) $request->query('betreff', ''),
            'body' => $this->closing()."\n\n".($fwd ? $this->forwardText($fwd) : ''),
        ]);
    }

    public function sendNew(Request $request)
    {
        $data = $this->validateMail($request, ['weiter' => ['nullable', 'integer'], 'mit_anhaengen' => ['nullable', 'in:1']]);
        $fwd = ! empty($data['weiter']) ? $this->find((int) $data['weiter']) : null;
        $to = $this->recipients($data['to']);
        $attachments = $this->uploads($request);
        if ($fwd && ! empty($data['mit_anhaengen'])) {
            foreach ($fwd->attachments ?? [] as $a) {
                if (Storage::disk('local')->exists($a['path'])) {
                    $attachments[] = ['name' => $a['name'], 'data' => (string) Storage::disk('local')->get($a['path']), 'mime' => $a['mime']];
                }
            }
        }
        $links = $fwd ? ['owner_id' => $fwd->owner_id, 'tenant_id' => $fwd->tenant_id] : Inbox::match($to[0] ?? '');
        $log = MailService::send($to, $data['subject'], $data['body'], $attachments, [
            'kind' => 'frei', 'ref_id' => $fwd?->id, 'owner_id' => $links['owner_id'], 'tenant_id' => $links['tenant_id'],
        ], $this->me());

        if ($log->status !== 'gesendet') {
            Audit::log('mail.failed', $log, 'E-Mail an '.($log->recipients ?: '–').': '.$log->error);

            return back()->withInput()->withErrors(['mail' => 'Nicht gesendet: '.$log->error]);
        }
        Audit::log('mail.sent', $log, 'E-Mail an '.$log->recipients.': '.$log->subject);

        return redirect()->to($fwd ? route('inbox.show', $fwd->id) : route('mail.index'))->with('status', 'Die E-Mail wurde gesendet.');
    }

    private function validateMail(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'to' => ['required', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:50000'],
            'dateien' => ['nullable', 'array', 'max:5'],
            'dateien.*' => self::UPLOAD_RULES,
        ], $extra), [
            'to.required' => 'Bitte mindestens eine Empfänger-Adresse eingeben.',
            'subject.required' => 'Bitte einen Betreff eingeben.',
            'body.required' => 'Bitte einen Text eingeben.',
            'dateien.max' => 'Höchstens 5 Anhänge.',
            'dateien.*.max' => 'Ein Anhang darf höchstens 10 MB groß sein.',
            'dateien.*.mimes' => 'Dieser Dateityp geht nicht als Anhang (PDF, Bilder, Office-Dateien, Text, ZIP).',
        ]);
    }

    /** @return string[] */
    private function recipients(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', $raw) ?: [] as $a) {
            $a = trim($a, " <>\t");
            if ($a !== '') {
                $out[] = $a;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 20);
    }

    /** @return array<int, array{name: string, data: string, mime: string}> */
    private function uploads(Request $request): array
    {
        $out = [];
        foreach ((array) $request->file('dateien', []) as $f) {
            if ($f && $f->isValid()) {
                $out[] = [
                    'name' => mb_substr(preg_replace('/[^\pL\pN._ -]/u', '_', $f->getClientOriginalName()), 0, 100),
                    'data' => (string) file_get_contents($f->getRealPath()),
                    'mime' => $f->getMimeType() ?: 'application/octet-stream',
                ];
            }
        }

        return $out;
    }

    private function closing(): string
    {
        $sig = MailSettings::get('mail_signature');

        return "\n\nMit freundlichen Grüßen\n".$this->me()->name.($sig !== '' ? "\n\n".$sig : '');
    }

    private function quote(ReceivedEmail $m): string
    {
        $lines = array_slice(explode("\n", $m->body), 0, 200);

        return 'Am '.($m->received_at ?? $m->created_at)->timezone('Europe/Berlin')->format('d.m.Y \u\m H:i').' schrieb '.$m->senderLabel().":\n"
            .implode("\n", array_map(fn ($l) => '> '.$l, $lines));
    }

    private function forwardText(ReceivedEmail $m): string
    {
        return "---------- Weitergeleitete Nachricht ----------\n"
            .'Von: '.($m->from_name ? $m->from_name.' <'.$m->from_address.'>' : $m->from_address)."\n"
            .'Datum: '.($m->received_at ?? $m->created_at)->timezone('Europe/Berlin')->format('d.m.Y H:i')."\n"
            .'Betreff: '.$m->subject."\n"
            .($m->to_addresses ? 'An: '.$m->to_addresses."\n" : '')
            ."\n".$m->body;
    }

    private function prefixed(string $prefix, string $subject): string
    {
        $clean = preg_replace('/^((re|aw|wg|fw|fwd)\s*:\s*)+/i', '', $subject);

        return mb_substr($prefix.': '.$clean, 0, 200);
    }

    /** @return array{in_reply_to?: string, references?: string} */
    private function threadFor(ReceivedEmail $m): array
    {
        if (! $m->message_id) {
            return [];
        }

        return ['in_reply_to' => $m->message_id, 'references' => trim(($m->references ? $m->references.' ' : '').$m->message_id)];
    }
}
