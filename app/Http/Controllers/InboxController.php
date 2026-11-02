<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\ReceivedEmail;
use App\Models\SentEmail;
use App\Support\Audit;
use App\Support\Mail\Inbox;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use App\Support\Mail\MailView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * E-Mail wie Outlook: Ordner | Liste | Lesebereich (großer Bildschirm), am Handy nacheinander.
 * Posteingang und Archiv = empfangene E-Mails aus dem Postfach, Gesendet = alles, was das CRM verschickt hat.
 */
class InboxController extends Controller
{
    use ScopesOwners;

    public const FOLDERS = ['eingang' => 'Posteingang', 'gesendet' => 'Gesendet', 'archiv' => 'Archiv'];

    public const FOLDER_ICONS = ['eingang' => 'inbox', 'gesendet' => 'send', 'archiv' => 'archive'];

    private const PER_PAGE = 40;

    private const UPLOAD_RULES = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,heic,doc,docx,xls,xlsx,odt,ods,txt,csv,zip'];

    private function find(int $id): ReceivedEmail
    {
        return ReceivedEmail::visibleTo($this->me())->findOrFail($id);
    }

    public function index(Request $request)
    {
        return $this->page($request, null);
    }

    /** Alte Adresse /posteingang/{id}: gleiche Seite mit geöffneter E-Mail. */
    public function show(Request $request, int $id)
    {
        return $this->page($request, $this->find($id));
    }

    private function page(Request $request, ?ReceivedEmail $open)
    {
        $folder = (string) $request->query('ordner', $open ? ($open->archived ? 'archiv' : 'eingang') : 'eingang');
        if (! isset(self::FOLDERS[$folder])) {
            $folder = 'eingang';
        }
        $q = trim((string) $request->query('q'));
        $page = max(1, (int) $request->query('seite', 1));
        $me = $this->me();

        // Liste
        if ($folder === 'gesendet') {
            $query = SentEmail::visibleTo($me)
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('recipients', 'like', '%'.$q.'%')->orWhere('subject', 'like', '%'.$q.'%')->orWhere('body', 'like', '%'.$q.'%')))
                ->orderByDesc('id');
        } else {
            $query = ReceivedEmail::visibleTo($me)
                ->where('archived', $folder === 'archiv')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('from_address', 'like', '%'.$q.'%')->orWhere('from_name', 'like', '%'.$q.'%')
                    ->orWhere('subject', 'like', '%'.$q.'%')->orWhere('body', 'like', '%'.$q.'%')))
                ->when($request->filled('mieter'), fn ($w) => $w->where('tenant_id', (int) $request->query('mieter')))
                ->orderByDesc('received_at')->orderByDesc('id');
        }
        $total = (clone $query)->count();
        $list = $query->forPage($page, self::PER_PAGE)->get();

        // Geöffnete E-Mail (Posteingang/Archiv) oder gesendete E-Mail
        $sent = null;
        if ($open === null && $folder !== 'gesendet' && $request->filled('id')) {
            $open = ReceivedEmail::visibleTo($me)->find((int) $request->query('id'));
        }
        if ($folder === 'gesendet' && $request->filled('id')) {
            $sent = SentEmail::visibleTo($me)->with(['user', 'tenant'])->find((int) $request->query('id'));
        }
        if ($open) {
            if ($open->read_at === null) {
                $open->forceFill(['read_at' => now(), 'read_by' => $me->id])->save();
            }
            Inbox::backfillHtml($open);
            $open->load(['tenant', 'vendor', 'owner', 'applicant']);
        }

        // Schreiben (neu, antworten, allen antworten, weiterleiten)
        $compose = null;
        if ($request->boolean('schreiben')) {
            $compose = $this->composeData($request);
        }

        return view('mail.outlook', [
            'folder' => $folder,
            'q' => $q,
            'page' => $page,
            'pages' => (int) ceil($total / self::PER_PAGE),
            'list' => $list,
            'open' => $open,
            'sent' => $sent,
            'compose' => $compose,
            'receive' => MailSettings::receiveConfigured(),
            'send' => MailSettings::configured(),
            'account' => MailSettings::get('mail_from_address') ?: MailSettings::get('mail_username'),
            'fromName' => MailSettings::fromName(),
            'lastFetch' => Inbox::lastFetch(),
            'lastError' => Inbox::lastError(),
            'unread' => ReceivedEmail::unreadCountFor($me),
            'images' => $request->boolean('bilder'),
        ]);
    }

    /** @return array{mode: string, to: string, cc: string, subject: string, body: string, ref: ?ReceivedEmail} */
    private function composeData(Request $request): array
    {
        $mode = in_array($request->query('wie'), ['antworten', 'allen', 'weiter'], true) ? (string) $request->query('wie') : 'neu';
        $ref = $mode !== 'neu' && $request->filled('id') ? $this->find((int) $request->query('id')) : null;
        if ($ref === null) {
            $mode = 'neu';
        }
        $to = (string) $request->query('an', '');
        $cc = '';
        $subject = (string) $request->query('betreff', '');
        $body = $this->closing();
        if ($ref) {
            if ($mode === 'weiter') {
                $to = '';
                $subject = $this->prefixed('WG', $ref->subject);
                $body .= "\n\n".$this->forwardText($ref);
            } else {
                $to = $ref->replyAddress();
                $subject = $this->prefixed('AW', $ref->subject);
                $body .= "\n\n".$this->quote($ref);
                if ($mode === 'allen') {
                    $own = array_map('mb_strtolower', array_filter([MailSettings::get('mail_from_address'), MailSettings::get('mail_username'), mb_strtolower($to)]));
                    $others = [];
                    foreach ([$ref->to_addresses, $ref->cc] as $line) {
                        foreach (\App\Support\Mail\Imap\MimeParser::addresses((string) $line) as [$addr]) {
                            if (! in_array($addr, $own, true)) {
                                $others[$addr] = $addr;
                            }
                        }
                    }
                    $cc = implode(', ', $others);
                }
            }
        }

        return ['mode' => $mode, 'to' => $to, 'cc' => $cc, 'subject' => $subject, 'body' => $body, 'ref' => $ref];
    }

    /** Inhalt der HTML-Fassung für den abgeschotteten Rahmen (keine Skripte, Bilder aus dem Internet nur auf Wunsch). */
    public function html(Request $request, int $id)
    {
        $mail = $this->find($id);
        abort_if((string) $mail->body_html === '', 404);
        $img = $request->boolean('bilder');

        return response(MailView::safeHtml($mail, $img), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; img-src data:".($img ? ' https: http:' : '')."; style-src 'unsafe-inline'".($img ? ' https:' : '')."; font-src data:".($img ? ' https:' : '')."; form-action 'none'; base-uri 'none'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
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

    public function attachment(int $id, int $n)
    {
        $mail = $this->find($id);
        $a = ($mail->attachments ?? [])[$n] ?? null;
        abort_if($a === null || ! Storage::disk('local')->exists($a['path']), 404);
        $safe = in_array($a['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
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

        return redirect()->route('inbox.index', ['ordner' => $mail->archived ? 'eingang' : 'archiv'])
            ->with('status', $mail->archived ? 'Ins Archiv verschoben.' : 'Zurück in den Posteingang.');
    }

    /**
     * E-Mail im CRM löschen (samt Anhängen). Im Postfach bei IONOS bleibt sie liegen; der Abruf holt nur neuere
     * Nachrichten, deshalb kommt sie nicht wieder.
     */
    public function destroy(int $id)
    {
        $mail = $this->find($id);
        $folder = $mail->archived ? 'archiv' : 'eingang';
        foreach ($mail->attachments ?? [] as $a) {
            try {
                if (! empty($a['path'])) {
                    Storage::disk('local')->delete($a['path']);
                }
            } catch (\Throwable) {
                // Ein fehlender Anhang hindert das Löschen nicht
            }
        }
        Audit::log('mail.deleted', null, 'E-Mail gelöscht: „'.mb_substr((string) $mail->subject, 0, 120).'“ von '.$mail->from_address);
        $mail->delete();

        return redirect()->route('inbox.index', ['ordner' => $folder])->with('status', 'E-Mail gelöscht.');
    }

    public function unread(int $id)
    {
        $mail = $this->find($id);
        $mail->forceFill(['read_at' => null, 'read_by' => null])->save();

        return redirect()->route('inbox.index', ['ordner' => $mail->archived ? 'archiv' : 'eingang'])->with('status', 'Als ungelesen markiert.');
    }

    public function reply(Request $request, int $id)
    {
        $mail = $this->find($id);
        $data = $this->validateMail($request);
        $log = MailService::send($this->recipients($data['to']), $data['subject'], $data['body'], $this->uploads($request), [
            'kind' => 'antwort', 'ref_id' => $mail->id, 'owner_id' => $mail->owner_id, 'tenant_id' => $mail->tenant_id,
        ], $this->me(), true, $this->threadFor($mail), $this->recipients((string) ($data['cc'] ?? '')));

        if ($log->status !== 'gesendet') {
            Audit::log('mail.failed', $log, 'Antwort an '.($log->recipients ?: '–').': '.$log->error);

            return back()->withInput()->withErrors(['mail' => 'Nicht gesendet: '.$log->error]);
        }
        $mail->forceFill(['replied_at' => now()])->save();
        Audit::log('mail.sent', $log, 'Antwort an '.$log->recipients.': '.$log->subject);

        return redirect()->route('inbox.index', ['ordner' => $mail->archived ? 'archiv' : 'eingang', 'id' => $mail->id])->with('status', 'Die Antwort wurde gesendet.');
    }

    /** Alte Adresse „Neue E-Mail“: führt in den Schreibbereich der Outlook-Ansicht. */
    public function write(Request $request)
    {
        $params = ['schreiben' => 1];
        if ($request->filled('weiter')) {
            $params += ['wie' => 'weiter', 'id' => (int) $request->query('weiter')];
        }
        foreach (['an', 'betreff'] as $k) {
            if ($request->filled($k)) {
                $params[$k] = (string) $request->query($k);
            }
        }

        return redirect()->route('inbox.index', $params);
    }

    /** Alte Adresse „Gesendete E-Mails“: Ordner Gesendet. */
    public function sentFolder(Request $request)
    {
        return redirect()->route('inbox.index', array_filter(['ordner' => 'gesendet', 'q' => $request->query('q')]));
    }

    public function sendNew(Request $request)
    {
        $data = $this->validateMail($request, ['weiter' => ['nullable', 'integer'], 'mit_anhaengen' => ['nullable', 'in:1']]);
        $fwd = ! empty($data['weiter']) ? $this->find((int) $data['weiter']) : null;
        $to = $this->recipients($data['to']);
        $attachments = $this->uploads($request);
        if ($fwd && ! empty($data['mit_anhaengen'])) {
            foreach ($fwd->attachments ?? [] as $a) {
                if (empty($a['inline']) && Storage::disk('local')->exists($a['path'])) {
                    $attachments[] = ['name' => $a['name'], 'data' => (string) Storage::disk('local')->get($a['path']), 'mime' => $a['mime']];
                }
            }
        }
        $links = $fwd ? ['owner_id' => $fwd->owner_id, 'tenant_id' => $fwd->tenant_id] : Inbox::match($to[0] ?? '');
        $log = MailService::send($to, $data['subject'], $data['body'], $attachments, [
            'kind' => 'frei', 'ref_id' => $fwd?->id, 'owner_id' => $links['owner_id'], 'tenant_id' => $links['tenant_id'],
        ], $this->me(), true, [], $this->recipients((string) ($data['cc'] ?? '')));

        if ($log->status !== 'gesendet') {
            Audit::log('mail.failed', $log, 'E-Mail an '.($log->recipients ?: '–').': '.$log->error);

            return back()->withInput()->withErrors(['mail' => 'Nicht gesendet: '.$log->error]);
        }
        Audit::log('mail.sent', $log, 'E-Mail an '.$log->recipients.': '.$log->subject);

        return redirect()->to($fwd ? route('inbox.index', ['ordner' => $fwd->archived ? 'archiv' : 'eingang', 'id' => $fwd->id]) : route('inbox.index', ['ordner' => 'gesendet', 'id' => $log->id]))
            ->with('status', 'Die E-Mail wurde gesendet.');
    }

    private function validateMail(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
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
        foreach (\App\Support\Mail\Imap\MimeParser::addresses(str_replace([';', "\n"], ',', $raw)) as [$addr]) {
            foreach (preg_split('/\s+/', $addr, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $a) {
                $out[mb_strtolower($a)] = $a;
            }
        }

        return array_slice(array_values($out), 0, 20);
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
        return MailView::SEPARATOR."\n"
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
