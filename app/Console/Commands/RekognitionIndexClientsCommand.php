<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\RekognitionService;
use Illuminate\Console\Command;

class RekognitionIndexClientsCommand extends Command
{
    protected $signature = 'rekognition:index-clients {--client= : ID client à réindexer uniquement}';

    protected $description = 'Indexe les photos clients dans la collection Rekognition (recherche par visage)';

    public function handle(RekognitionService $rekognition): int
    {
        if (! $rekognition->enabled()) {
            $this->warn('Rekognition désactivé ou credentials AWS manquants.');

            return self::FAILURE;
        }

        $rekognition->ensureCollection();

        $clientId = $this->option('client');
        $query = Client::query()->whereNotNull('photo')->where('photo', '!=', '');

        if ($clientId) {
            $query->where('id_client', (int) $clientId);
        }

        $clients = $query->get();
        if ($clients->isEmpty()) {
            $this->warn('Aucun client avec photo.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($clients as $client) {
            if ($rekognition->indexClientFromStoredPhoto($client)) {
                $ok++;
                $this->line("OK client #{$client->id_client} — {$client->nom_complet}");
            } else {
                $fail++;
                $this->warn("Échec client #{$client->id_client} — {$client->nom_complet}");
            }
        }

        $this->newLine();
        $this->info("Indexation terminée : {$ok} réussi(s), {$fail} échec(s).");
        $this->info('Visages dans la collection : '.$rekognition->countIndexedFaces());

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
