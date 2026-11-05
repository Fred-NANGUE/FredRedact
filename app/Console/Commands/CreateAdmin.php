<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'users:create-admin {email} {--name=}';

    protected $description = 'Crée un compte administrateur ou attribue le rôle à un compte existant.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: $this->ask('Nom complet'));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update(['name' => $name, 'role' => 'admin']);
            $this->info('Le rôle administrateur a été attribué au compte existant.');

            return self::SUCCESS;
        }

        $password = $this->secret('Mot de passe (8 caractères minimum)');
        $confirmation = $this->secret('Confirmer le mot de passe');
        if (! $password || strlen($password) < 8 || $password !== $confirmation) {
            $this->error('Le mot de passe est trop court ou les confirmations diffèrent.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info('Compte administrateur créé.');

        return self::SUCCESS;
    }
}
