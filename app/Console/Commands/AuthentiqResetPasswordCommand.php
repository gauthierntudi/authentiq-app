<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AuthentiqResetPasswordCommand extends Command
{
    protected $signature = 'authentiq:reset-password
                            {login : Email ou téléphone}
                            {password : Nouveau mot de passe (min. 8 caractères)}
                            {--admin : Passer le rôle à admin}';

    protected $description = 'Réinitialise le mot de passe d\'un utilisateur (table USERS)';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $password = (string) $this->argument('password');

        if (strlen($password) < 8) {
            $this->error('Le mot de passe doit faire au moins 8 caractères.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $login)
            ->orWhere('tel', $login)
            ->first();

        if (! $user) {
            $this->error("Aucun utilisateur pour : {$login}");
            $this->line('Vérifiez la base : php artisan authentiq:db-status');

            return self::FAILURE;
        }

        $user->password = password_hash($password, PASSWORD_DEFAULT);
        if ($this->option('admin')) {
            $user->role = 'admin';
        }
        $user->save();

        $this->info('Mot de passe mis à jour.');
        $this->line("  ID : {$user->id_user}");
        $this->line("  Nom : {$user->nom_complet}");
        $this->line("  Email : {$user->email}");
        $this->line("  Tél : {$user->tel}");
        $this->line("  Rôle : {$user->role}");

        return self::SUCCESS;
    }
}
