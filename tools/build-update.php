<?php
/**
 * Baut ein signiertes Update-Paket (*.crmupdate) für das Immobilien-CRM.
 * php build-update.php --name=paket7 --title="…" --src=/pfad/neu --base=/pfad/alt --files="a.php b.php"
 *   [--migrations="database/migrations/x.php"] [--requires="app/…"] [--selftest=mieten:selbsttest]
 *   [--desc-file=beschreibung.txt] --key=update-private.pem --out=paket7.crmupdate
 * old_sha256 kommt aus --base (Stand vor dem Paket); fehlt die Datei dort, ist sie neu.
 */
$o = getopt('', ['name:', 'title:', 'src:', 'base:', 'files:', 'migrations::', 'requires::', 'selftest::', 'desc-file::', 'key:', 'out:']);
foreach (['name', 'title', 'src', 'base', 'files', 'key', 'out'] as $k) {
    if (empty($o[$k])) { fwrite(STDERR, "--$k fehlt\n"); exit(1); }
}
$split = fn ($s) => array_values(array_filter(preg_split('/\s+/', trim((string) $s))));
$files = [];
foreach ($split($o['files']) as $path) {
    $abs = rtrim($o['src'], '/').'/'.$path;
    if (! is_file($abs)) { fwrite(STDERR, "fehlt: $abs\n"); exit(1); }
    $content = file_get_contents($abs);
    $old = rtrim($o['base'], '/').'/'.$path;
    $files[] = ['path' => $path, 'old_sha256' => is_file($old) ? hash_file('sha256', $old) : null, 'sha256' => hash('sha256', $content), 'content_b64' => base64_encode($content)];
}
$manifest = [
    'name' => $o['name'],
    'title' => $o['title'],
    'description' => isset($o['desc-file']) ? trim(file_get_contents($o['desc-file'])) : '',
    'created' => date('d.m.Y H:i'),
    'files' => $files,
    'migrations' => $split($o['migrations'] ?? ''),
    'requires_files' => $split($o['requires'] ?? ''),
    'selftest' => $o['selftest'] ?? null,
];
$payload = base64_encode(gzencode(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 9));
$key = openssl_pkey_get_private(file_get_contents($o['key']));
openssl_sign($payload, $sig, $key, OPENSSL_ALGO_SHA256) || exit("Signieren fehlgeschlagen\n");
file_put_contents($o['out'], json_encode(['format' => 'crm-update-1', 'key' => '2026-10', 'payload' => $payload, 'signature' => base64_encode($sig)]));
echo "OK ".$o['out']." (".count($files)." Dateien)\n";
