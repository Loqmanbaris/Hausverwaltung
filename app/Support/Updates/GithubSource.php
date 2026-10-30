<?php

namespace App\Support\Updates;

use App\Models\SystemUpdate;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Holt signierte Update-Pakete (.crmupdate) aus einem GitHub-Repository (Paket 30).
 * Die Pakete bleiben digital unterschrieben: Das CRM nimmt nur Pakete an, die zum eingebauten öffentlichen Schlüssel passen.
 * Ein manipuliertes Repository kann deshalb keinen Programmcode einschleusen.
 */
class GithubSource
{
    public const API = 'https://api.github.com';

    public const MAX_BYTES = 20 * 1024 * 1024;

    public static function repo(): string
    {
        return trim((string) Settings::get('update_github_repo', ''));
    }

    public static function path(): string
    {
        return trim((string) Settings::get('update_github_path', 'updates'), '/ ');
    }

    public static function branch(): string
    {
        return trim((string) Settings::get('update_github_branch', 'main'));
    }

    public static function hasToken(): bool
    {
        return (string) Settings::get('update_github_token_enc', '') !== '';
    }

    public static function configured(): bool
    {
        return self::validRepo(self::repo());
    }

    public static function validRepo(string $repo): bool
    {
        return preg_match('#^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*$#', $repo) === 1;
    }

    public static function validPath(string $path): bool
    {
        return $path === '' || (preg_match('#^[A-Za-z0-9_./-]+$#', $path) === 1 && ! str_contains($path, '..') && ! str_starts_with($path, '/'));
    }

    public static function validBranch(string $branch): bool
    {
        return preg_match('#^[A-Za-z0-9][A-Za-z0-9_./-]{0,99}$#', $branch) === 1 && ! str_contains($branch, '..');
    }

    /** Dateiname eines Pakets: Buchstaben, Ziffern, Punkt, Strich, Unterstrich, Endung .crmupdate. */
    public static function validFileName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,100}\.crmupdate$/', $name) === 1;
    }

    /** Speichert die Einstellungen. Ein leerer Token lässt den gespeicherten unverändert, $clearToken entfernt ihn. */
    public static function save(string $repo, string $path, string $branch, ?string $token, bool $clearToken = false): void
    {
        $values = ['update_github_repo' => $repo, 'update_github_path' => $path, 'update_github_branch' => $branch];
        if ($clearToken) {
            $values['update_github_token_enc'] = null;
        } elseif ($token !== null && trim($token) !== '') {
            $values['update_github_token_enc'] = Crypt::encryptString(trim($token));
        }
        Settings::putMany($values);
    }

    private static function token(): ?string
    {
        $enc = (string) Settings::get('update_github_token_enc', '');
        if ($enc === '') {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function request(string $accept)
    {
        $req = Http::withHeaders([
            'Accept' => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'Immobilien-CRM-Updates',
        ])->timeout(30)->connectTimeout(10);
        $token = self::token();

        return $token !== null ? $req->withToken($token) : $req;
    }

    private static function url(string $file = ''): string
    {
        $path = self::path();
        $full = trim($path.'/'.$file, '/');

        return self::API.'/repos/'.self::repo().'/contents'.($full !== '' ? '/'.implode('/', array_map('rawurlencode', explode('/', $full))) : '');
    }

    /** @throws RuntimeException mit verständlicher Meldung */
    private static function fail(int $status): never
    {
        throw new RuntimeException(match (true) {
            $status === 401 => 'GitHub lehnt den Zugangsschlüssel (Token) ab. Er ist ungültig oder abgelaufen.',
            $status === 403 => 'GitHub verweigert den Zugriff. Der Token hat zu wenig Rechte, oder das Abfrage-Limit ist erreicht.',
            $status === 404 => 'Repository, Ordner oder Datei wurde nicht gefunden. Bei einem privaten Repository fehlt meist der Token oder sein Lesezugriff auf dieses Repository.',
            default => 'GitHub antwortet mit einem Fehler (Code '.$status.').',
        });
    }

    /**
     * Pakete im Ordner des Repositories, neueste zuletzt.
     *
     * @return array<int, array{name: string, size: int, installed: bool}>
     */
    public static function list(): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Es ist noch kein Repository eingetragen.');
        }
        try {
            $res = self::request('application/vnd.github+json')->get(self::url(), ['ref' => self::branch()]);
        } catch (ConnectionException) {
            throw new RuntimeException('Der Server erreicht GitHub nicht. Bitte später noch einmal versuchen. Falls es bleibt: Prüfe, ob der Webspace ausgehende Verbindungen erlaubt.');
        }
        if (! $res->successful()) {
            self::fail($res->status());
        }
        $items = $res->json();
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Der Pfad zeigt auf eine Datei, nicht auf einen Ordner.');
        }

        $installed = SystemUpdate::query()->whereIn('status', ['installiert', 'test_fehler'])->pluck('name')->map(fn ($n) => strtolower((string) $n))->all();
        $out = [];
        foreach ($items as $it) {
            $name = (string) ($it['name'] ?? '');
            if (($it['type'] ?? '') !== 'file' || ! self::validFileName($name)) {
                continue;
            }
            $out[] = ['name' => $name, 'size' => (int) ($it['size'] ?? 0), 'installed' => in_array(self::packageKey($name), $installed, true)];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $out;
    }

    /** „paket27-mieterhoehung.crmupdate“ wird zu „paket27“ (Name des Pakets im Update-Verlauf). */
    public static function packageKey(string $fileName): string
    {
        $base = preg_replace('/\.crmupdate$/i', '', $fileName) ?? $fileName;

        return strtolower(explode('-', $base)[0]);
    }

    /** Lädt ein Paket und gibt den Rohinhalt zurück. Die Signatur prüft danach UpdatePackage::fromSigned. */
    public static function fetch(string $name): string
    {
        if (! self::validFileName($name)) {
            throw new RuntimeException('Ungültiger Dateiname.');
        }
        if (! self::configured()) {
            throw new RuntimeException('Es ist noch kein Repository eingetragen.');
        }
        try {
            $res = self::request('application/vnd.github.raw+json')->get(self::url($name), ['ref' => self::branch()]);
        } catch (ConnectionException) {
            throw new RuntimeException('Der Server erreicht GitHub nicht. Bitte später noch einmal versuchen.');
        }
        if (! $res->successful()) {
            self::fail($res->status());
        }
        $body = $res->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Die Datei ist leer oder zu groß.');
        }

        return $body;
    }
}
