<?php

namespace App\Console\Commands;

use App\Services\RekognitionService;
use Illuminate\Console\Command;

class EnsureRekognitionCollection extends Command
{
    protected $signature = 'rekognition:ensure-collection';

    protected $description = 'Crée la collection Rekognition pour les visages clients si elle n\'existe pas';

    public function handle(RekognitionService $rekognition): int
    {
        if (! $rekognition->enabled()) {
            $this->warn('Rekognition désactivé ou credentials AWS manquants.');

            return self::FAILURE;
        }

        $rekognition->ensureCollection();
        $this->info('Collection « '.$rekognition->collectionId().' » prête.');

        return self::SUCCESS;
    }
}
