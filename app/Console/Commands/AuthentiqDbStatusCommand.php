<?php

namespace App\Console\Commands;

use App\Models\Encodage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthentiqDbStatusCommand extends Command
{
    protected $signature = 'authentiq:db-status';

    protected $description = 'Vérifie la connexion MySQL et le contenu des tables Authentiq';

    public function handle(): int
    {
        $this->info('=== État base de données Authentiq ===');
        $this->line('Connexion : '.config('database.default'));
        $this->line('Host : '.config('database.connections.mysql.host'));
        $this->line('Database : '.config('database.connections.mysql.database'));

        try {
            DB::connection()->getPdo();
            $this->info('Connexion MySQL : OK');
        } catch (\Throwable $e) {
            $this->error('Connexion MySQL : ÉCHEC — '.$e->getMessage());

            return self::FAILURE;
        }

        $tables = ['USERS', 'CLIENTS', 'ENCODAGES', 'ENCODAGES_PAGES', 'OTP_CODES', 'jobs', 'sessions'];
        foreach ($tables as $table) {
            $exists = Schema::hasTable($table);
            $count = $exists ? DB::table($table)->count() : 0;
            $this->line(sprintf('  %-18s %s (%d lignes)', $table, $exists ? '✓' : '✗', $count));
        }

        $users = User::query()->count();
        if ($users === 0) {
            $this->newLine();
            $this->warn('Aucun utilisateur dans USERS → connexion impossible (« Identifiants incorrects »).');
            $this->line('Importez votre dump Valet dans MySQL Cloud, ou exécutez :');
            $this->line('  php artisan authentiq:import-legacy-schema');
            $this->line('  php artisan authentiq:reset-password email@example.com "MotDePasse" --admin');

        } else {
            $this->newLine();
            $this->info("Utilisateurs trouvés : {$users}");
        }

        if ($users > 0) {
            User::query()->orderBy('id_user')->limit(5)->get(['id_user', 'email', 'tel', 'role'])
                ->each(fn (User $u) => $this->line("  #{$u->id_user} {$u->email} / {$u->tel} ({$u->role})"));

            $this->line('Encodages : '.Encodage::query()->count());
        }

        return self::SUCCESS;
    }
}
