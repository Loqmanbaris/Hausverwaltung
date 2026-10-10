<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetTwoFactor extends Command
{
    protected $signature = 'app:reset-two-factor {email : E-Mail-Adresse des Benutzers}';

    protected $description = 'Setzt die Zwei-Faktor-Anmeldung eines Benutzers zurück (Notfall, z. B. Handy verloren)';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->error('Kein Benutzer mit dieser E-Mail-Adresse gefunden.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => 0,
            'two_factor_recovery_codes' => null,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        Audit::log('two_factor.reset', 'Zwei-Faktor per Server-Befehl zurückgesetzt für '.$user->email, $user, label: 'Server-Befehl');

        $this->info('Zwei-Faktor zurückgesetzt. Bei der nächsten Anmeldung wird die App neu eingerichtet.');

        return self::SUCCESS;
    }
}
