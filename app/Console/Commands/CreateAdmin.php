<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Legt einen Administrator an';

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()
            && ! $this->confirm('Es gibt bereits einen Administrator. Trotzdem einen weiteren anlegen?', false)) {
            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Name'));
        $email = Str::lower(trim((string) $this->ask('E-Mail-Adresse')));
        $password = (string) $this->secret('Passwort (mindestens 12 Zeichen, Groß- und Kleinbuchstaben und eine Zahl)');
        $repeat = (string) $this->secret('Passwort wiederholen');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $repeat],
            [
                'name' => ['required', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        Audit::log('user.created', 'Administrator per Server-Befehl angelegt: '.$email, $user, label: 'Server-Befehl');

        $this->info('Administrator angelegt. Du kannst dich jetzt im Browser anmelden.');

        return self::SUCCESS;
    }
}
