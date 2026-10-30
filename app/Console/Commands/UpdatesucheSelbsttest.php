<?php

namespace App\Console\Commands;

use App\Support\Updates\GithubSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 30 (Updates von GitHub). */
class UpdatesucheSelbsttest extends Command
{
    protected $signature = 'updatesuche:selbsttest';

    protected $description = 'Selbsttest Updates von GitHub (Paket 30)';

    public function handle(): int
    {
        $checks = [
            'Repository-Name' => GithubSource::validRepo('meinname/crm-updates') && ! GithubSource::validRepo('../etc/passwd') && ! GithubSource::validRepo('nur-ein-name') && ! GithubSource::validRepo('a/b/c'),
            'Ordner' => GithubSource::validPath('updates') && GithubSource::validPath('') && ! GithubSource::validPath('../geheim') && ! GithubSource::validPath('/root'),
            'Dateiname' => GithubSource::validFileName('paket27-mieterhoehung.crmupdate') && ! GithubSource::validFileName('../paket.crmupdate') && ! GithubSource::validFileName('paket.php') && ! GithubSource::validFileName('a/b.crmupdate'),
            'Paketname aus Dateiname' => GithubSource::packageKey('paket27-mieterhoehung.crmupdate') === 'paket27' && GithubSource::packageKey('Paket8.crmupdate') === 'paket8',
            'Seiten' => Route::has('updates.github') && Route::has('updates.github.save') && Route::has('updates.github.fetch'),
            'Ansicht' => view()->exists('updates.github'),
        ];
        $this->line('Selbsttest Paket 30 (Updates von GitHub)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
