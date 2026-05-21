<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\RekognitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IndexClientFaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $clientId) {}

    public function handle(RekognitionService $rekognition): void
    {
        if (! $rekognition->enabled()) {
            return;
        }

        $client = Client::query()->find($this->clientId);
        if (! $client) {
            return;
        }

        try {
            if (! $rekognition->indexClientFromStoredPhoto($client)) {
                Log::warning('Rekognition index failed', ['client_id' => $this->clientId]);
            }
        } catch (\Throwable $e) {
            Log::warning('Rekognition index failed', [
                'client_id' => $this->clientId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
