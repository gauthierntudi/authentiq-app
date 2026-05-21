<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientPhotoStorage;
use Illuminate\Console\Command;

class AuthentiqMigrateClientPhotosCommand extends Command
{
    protected $signature = 'authentiq:migrate-client-photos
                            {--dry-run : Affiche les actions sans modifier la base ni S3}';

    protected $description = 'Copie les photos clients (uploads locaux) vers le disque configuré (S3 en production)';

    public function handle(ClientPhotoStorage $photos): int
    {
        $disk = $photos->diskName();
        $this->info("Disque photos clients : {$disk}");

        if ($disk !== 's3') {
            $this->warn('AUTHENTIQ_DOCUMENTS_DISK n\'est pas « s3 » — migration utile surtout en production Laravel Cloud.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $clients = Client::query()
            ->whereNotNull('photo')
            ->where('photo', '!=', '')
            ->orderBy('id_client')
            ->get();

        if ($clients->isEmpty()) {
            $this->warn('Aucun client avec photo en base.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach ($clients as $client) {
            $path = $client->photo;
            if ($dryRun) {
                $exists = $photos->url($path) !== null;
                $this->line(sprintf(
                    '  #%d %s — %s',
                    $client->id_client,
                    $client->nom_complet,
                    $exists ? 'déjà accessible' : 'à migrer (fichier local requis)'
                ));
                $exists ? $skip++ : $fail++;

                continue;
            }

            if ($photos->migrateClientPhoto($client)) {
                $client->refresh();
                $this->line("  OK #{$client->id_client} → {$client->photo}");
                $ok++;
            } else {
                $this->warn("  Échec #{$client->id_client} ({$path}) — fichier introuvable localement ni sur S3");
                $fail++;
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry-run : {$clients->count()} client(s), {$skip} déjà OK, {$fail} à migrer."
            : "Terminé : {$ok} migré(s), {$fail} échec(s).");

        if ($fail > 0 && $disk === 's3') {
            $this->comment('Astuce : copiez public/uploads/clients/ depuis Valet sur la machine de deploy, ou relancez depuis un poste qui a encore les fichiers.');
        }

        return $fail > 0 && ! $dryRun ? self::FAILURE : self::SUCCESS;
    }
}
