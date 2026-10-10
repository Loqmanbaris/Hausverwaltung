<?php

namespace App\Http\Controllers;

use App\Models\SystemUpdate;
use App\Support\Audit;
use App\Support\Updates\UpdatePackage;
use App\Support\Updates\Updater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Update-Seite (nur Administrator): signiertes Paket hochladen, prüfen, einspielen, Selbsttest, zurücknehmen.
 * Einspielen und Zurücknehmen verlangen zusätzlich das Passwort.
 */
class UpdateController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesOwners;

    public function index()
    {
        return view('updates.index', [
            'updates' => SystemUpdate::query()->with('installer')->orderByDesc('id')->limit(30)->get(),
            'writable' => is_writable(base_path('app')) && is_writable(base_path('resources/views')) && is_writable(base_path('routes')),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate(['paket' => ['required', 'file', 'max:20480']], [
            'paket.required' => 'Bitte die Paket-Datei (.crmupdate) auswählen.',
            'paket.max' => 'Die Datei ist zu groß.',
            'paket.file' => 'Die Datei konnte nicht hochgeladen werden.',
        ]);

        $raw = (string) file_get_contents($request->file('paket')->getRealPath());
        try {
            $package = UpdatePackage::fromSigned($raw);
        } catch (RuntimeException $e) {
            Audit::log('update.rejected', 'Update-Paket abgelehnt: '.$e->getMessage());

            return back()->withErrors(['paket' => $e->getMessage()]);
        }

        $token = bin2hex(random_bytes(16));
        $dir = Updater::storageDir().'/pending';
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        // Ältere, nicht eingespielte Uploads wegräumen
        foreach (glob($dir.'/*.crmupdate') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        file_put_contents($dir.'/'.$token.'.crmupdate', $raw);

        return redirect()->route('updates.show', ['paket' => $token]);
    }

    public function show(Request $request)
    {
        [$token, $package] = $this->pending((string) $request->query('paket'));
        $check = Updater::check($package);

        return view('updates.show', [
            'token' => $token,
            'package' => $package,
            'check' => $check,
            'problems' => Updater::problems($check),
        ]);
    }

    public function install(Request $request)
    {
        [$token, $package] = $this->pending((string) $request->input('paket'));
        if (! Hash::check((string) $request->input('password'), (string) $this->me()->password)) {
            return redirect()->route('updates.show', ['paket' => $token])->withErrors(['password' => 'Das Passwort stimmt nicht.']);
        }

        try {
            $update = Updater::install($package, $this->me());
        } catch (RuntimeException $e) {
            return redirect()->route('updates.show', ['paket' => $token])->withErrors(['install' => $e->getMessage()]);
        }
        @unlink(Updater::storageDir().'/pending/'.$token.'.crmupdate');

        // Der Selbsttest läuft in der nächsten Anfrage, dann schon mit dem neuen Programmcode
        return redirect()->route('updates.result', $update)->with('run_selftest', $update->id);
    }

    public function result(Request $request, SystemUpdate $update)
    {
        $test = null;
        if ((int) $request->session()->get('run_selftest') === (int) $update->id) {
            $test = Updater::selftest($update);
            $update->refresh();
        }

        return view('updates.result', compact('update', 'test'));
    }

    public function rollback(Request $request, SystemUpdate $update)
    {
        if (! in_array($update->status, ['installiert', 'test_fehler'], true)) {
            return back()->withErrors(['rollback' => 'Dieses Update ist nicht (mehr) eingespielt.']);
        }
        $newer = SystemUpdate::query()->where('id', '>', $update->id)->whereIn('status', ['installiert', 'test_fehler'])->exists();
        if ($newer) {
            return back()->withErrors(['rollback' => 'Danach wurden weitere Updates eingespielt. Bitte zuerst diese zurücknehmen.']);
        }
        if (! Hash::check((string) $request->input('password'), (string) $this->me()->password)) {
            return back()->withErrors(['password' => 'Das Passwort stimmt nicht.']);
        }

        Updater::rollback($update);

        return redirect()->route('updates.index')->with('status', 'Das Update „'.$update->title.'“ wurde zurückgenommen.');
    }

    /**
     * Programmstand als ZIP-Datei für Claude: nur Programmcode, ohne .env (Passwörter), ohne storage (Belege, Sicherungen)
     * und ohne vendor. Dazu eine Liste der eingespielten Tabellen-Stände und Updates.
     */
    public function exportSource()
    {
        $root = base_path();
        $dirs = ['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes'];
        $files = ['artisan', 'composer.json', 'composer.lock', 'public/index.php', 'public/.htaccess', 'public/robots.txt'];

        $list = [];
        foreach ($dirs as $d) {
            if (! is_dir($root.'/'.$d)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$d, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = substr($f->getPathname(), strlen($root) + 1);
                if ($f->isFile() && ! str_starts_with($rel, 'bootstrap/cache/') && ! str_contains($rel, '.tmp-') && $f->getSize() < 5 * 1024 * 1024) {
                    $list[] = $rel;
                }
            }
        }
        foreach ($files as $f) {
            if (is_file($root.'/'.$f)) {
                $list[] = $f;
            }
        }
        sort($list);

        $info = "Programmstand vom ".now()->timezone('Europe/Berlin')->format('d.m.Y H:i')."\n\nTabellen-Stände (migrations):\n"
            .implode("\n", \Illuminate\Support\Facades\DB::table('migrations')->orderBy('id')->pluck('migration')->all())
            ."\n\nUpdates über die Update-Seite:\n"
            .SystemUpdate::query()->orderBy('id')->get()->map(fn ($u) => $u->name.' – '.$u->status.' – '.$u->installed_at?->format('d.m.Y H:i'))->implode("\n")."\n";

        $path = (string) tempnam(sys_get_temp_dir(), 'crm-code-');
        $zip = new \App\Support\Datev\ZipWriter($path);
        $zip->addFile('PROGRAMMSTAND.txt', $info);
        foreach ($list as $rel) {
            $zip->addFile('app/'.$rel, (string) file_get_contents($root.'/'.$rel));
        }
        $zip->close();
        Audit::log('update.source_exported', 'Programmstand für Claude heruntergeladen ('.count($list).' Dateien)');

        return response()->download($path, 'programmstand-'.date('Y-m-d-Hi').'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    /** @return array{0: string, 1: UpdatePackage} */
    private function pending(string $token): array
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $token) === 1, 404);
        $file = Updater::storageDir().'/pending/'.$token.'.crmupdate';
        abort_unless(is_file($file), 404);

        try {
            return [$token, UpdatePackage::fromSigned((string) file_get_contents($file))];
        } catch (RuntimeException) {
            abort(404);
        }
    }
}
