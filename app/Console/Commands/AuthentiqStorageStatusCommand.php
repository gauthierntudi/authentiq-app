<?php

namespace App\Console\Commands;

use App\Services\DocumentStorage;
use Illuminate\Console\Command;

class AuthentiqStorageStatusCommand extends Command
{
    protected $signature = 'authentiq:storage-status';

    protected $description = 'Vérifie la config stockage documents / photos (S3, bucket AWS)';

    public function handle(DocumentStorage $storage): int
    {
        $disk = $storage->diskName();
        $this->info("AUTHENTIQ_DOCUMENTS_DISK = {$disk}");

        if ($disk === 's3') {
            $bucket = config('filesystems.disks.s3.bucket');
            $region = config('filesystems.disks.s3.region');
            $key = config('filesystems.disks.s3.key');
            $endpoint = config('filesystems.disks.s3.endpoint');

            $this->line('AWS_BUCKET: '.($bucket ?: '(vide — erreur upload photos/documents)'));
            $this->line('AWS_DEFAULT_REGION: '.($region ?: '(vide)'));
            $this->line('AWS_ACCESS_KEY_ID: '.($key ? 'défini' : '(vide)'));
            $this->line('AWS_ENDPOINT: '.($endpoint ?: '(défaut AWS)'));

            if (! $storage->cloudDiskReady()) {
                $this->error('S3 non prêt : configurez Object Storage sur Laravel Cloud ou remplissez AWS_*.');

                return self::FAILURE;
            }

            $this->info('S3 : configuration OK.');
        } else {
            $this->info('Mode local (public/uploads).');
        }

        return self::SUCCESS;
    }
}
