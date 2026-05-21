<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AuthentiqDeployCommand extends Command
{
    protected $signature = 'authentiq:deploy
                            {--skip-import : Ne pas tenter l\'import schema-legacy.sql}
                            {--skip-aws : Ne pas exécuter rekognition:ensure-collection}';

    protected $description = 'Post-déploiement Authentiq : schéma legacy, migrations Laravel, cache, AWS';

    public function handle(): int
    {
        $this->info('=== Déploiement Authentiq ===');

        if (! $this->option('skip-import')) {
            $this->call('authentiq:import-legacy-schema');
        }

        $this->info('Migrations Laravel…');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(trim(Artisan::output()));

        if (! $this->option('skip-aws') && config('authentiq.rekognition_enabled')) {
            $this->call('rekognition:ensure-collection');
        }

        if ($this->laravel->environment('production')) {
            $this->info('Cache production…');
            foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
                Artisan::call($cmd);
            }
        } else {
            Artisan::call('config:clear');
        }

        $this->newLine();
        $this->info('Déploiement terminé.');
        $this->line('Sur Laravel Cloud, activez aussi :');
        $this->line('  • Scheduler (encodage:mark-expired)');
        $this->line('  • Background process : php artisan queue:work --queue=default --tries=2 --timeout=120');
        $this->line('Voir DEPLOY-LARAVEL-CLOUD.md');

        return self::SUCCESS;
    }
}
