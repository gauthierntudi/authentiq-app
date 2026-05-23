<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ClientPhotoVerificationService
{
    public function __construct(
        private RekognitionService $rekognition,
        private ClientKycService $kyc,
    ) {}

    public function canChangePhoto(?Client $client): bool
    {
        return $client !== null && $this->kyc->canEditProfile($client);
    }

    /**
     * @return array{
     *   mode: string,
     *   session_id: string|null,
     *   web_url: string|null,
     *   region: string|null,
     *   min_frames: int,
     *   message: string
     * }
     */
    public function startVerificationSession(Client $client): array
    {
        if (! $this->canChangePhoto($client)) {
            return [
                'mode' => 'denied',
                'session_id' => null,
                'web_url' => null,
                'region' => null,
                'min_frames' => 0,
                'message' => 'Modification de photo impossible après validation KYC.',
            ];
        }

        if (! $this->rekognition->enabled()) {
            return [
                'mode' => 'disabled',
                'session_id' => null,
                'web_url' => null,
                'region' => null,
                'min_frames' => 0,
                'message' => 'Reconnaissance faciale indisponible. Contactez le support.',
            ];
        }

        return [
            'mode' => 'multi_capture',
            'session_id' => null,
            'web_url' => null,
            'region' => (string) config('authentiq.aws_region', 'us-east-1'),
            'min_frames' => (int) config('authentiq.photo_presence_min_frames', 3),
            'message' => 'Prenez plusieurs selfies en direct pour confirmer votre présence.',
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $frames
     * @return array{token: string|null, message: string}
     */
    public function verifyMultiCapturePresence(Client $client, array $frames): array
    {
        if (! $this->canChangePhoto($client)) {
            return ['token' => null, 'message' => 'Modification de photo impossible.'];
        }

        if (! $this->rekognition->enabled()) {
            return ['token' => null, 'message' => 'Reconnaissance faciale indisponible.'];
        }

        $minFrames = (int) config('authentiq.photo_presence_min_frames', 3);
        if (count($frames) < $minFrames) {
            return ['token' => null, 'message' => "Envoyez au moins {$minFrames} selfies en direct."];
        }

        $storedBytes = $this->rekognition->readClientPhotoBytes($client);
        $boxes = [];
        $referenceBytes = null;

        foreach ($frames as $index => $file) {
            $bytes = file_get_contents($file->getRealPath());
            if ($bytes === false || $bytes === '') {
                return ['token' => null, 'message' => 'Photo illisible (frame '.($index + 1).').'];
            }

            $detect = $this->rekognition->detectPrimaryFaceBox($bytes);
            if (! $detect['ok'] || ! $detect['box']) {
                return ['token' => null, 'message' => $detect['message']];
            }
            $boxes[] = $detect['box'];
            $referenceBytes ??= $bytes;

            if ($storedBytes) {
                $compare = $this->rekognition->compareFacesBytes($storedBytes, $bytes);
                if (! $compare['match']) {
                    return ['token' => null, 'message' => 'Vous devez être la même personne que sur la photo actuelle.'];
                }
            }
        }

        if (! $this->faceBoxesMoved($boxes)) {
            return [
                'token' => null,
                'message' => 'Présence physique non confirmée. Ne présentez pas une photo imprimée : utilisez la caméra en direct.',
            ];
        }

        $token = $this->issueToken($client->id_client, [
            'method' => 'multi_capture',
            'reference_bytes' => $referenceBytes,
        ]);

        return ['token' => $token, 'message' => 'Identité confirmée. Vous pouvez choisir une nouvelle photo.'];
    }

    /**
     * @return array{client: Client|null, message: string}
     */
    public function applyNewPhoto(Client $client, UploadedFile $photo, string $verificationToken): array
    {
        if (! $this->canChangePhoto($client)) {
            return ['client' => null, 'message' => 'Modification de photo impossible après validation KYC.'];
        }

        $payload = $this->consumeToken($client->id_client, $verificationToken);
        if ($payload === null) {
            return ['client' => null, 'message' => 'Vérification expirée. Refaites la confirmation de présence.'];
        }

        $newBytes = file_get_contents($photo->getRealPath());
        if ($newBytes === false || $newBytes === '') {
            return ['client' => null, 'message' => 'Photo illisible.'];
        }

        if (! $this->rekognition->enabled()) {
            return ['client' => null, 'message' => 'Reconnaissance faciale indisponible.'];
        }

        $storedBytes = $this->rekognition->readClientPhotoBytes($client);
        $referenceBytes = $payload['reference_bytes'] ?? null;

        if ($storedBytes) {
            $compare = $this->rekognition->compareFacesBytes($storedBytes, $newBytes);
            if (! $compare['match']) {
                return ['client' => null, 'message' => 'La nouvelle photo doit montrer la même personne que la photo actuelle.'];
            }
        } elseif ($referenceBytes) {
            $compare = $this->rekognition->compareFacesBytes($referenceBytes, $newBytes);
            if (! $compare['match']) {
                return ['client' => null, 'message' => 'La nouvelle photo doit correspondre à la personne vérifiée.'];
            }
        } else {
            $detect = $this->rekognition->detectPrimaryFaceBox($newBytes);
            if (! $detect['ok']) {
                return ['client' => null, 'message' => $detect['message']];
            }
        }

        return ['client' => $client, 'message' => 'Photo validée.'];
    }

    /** @param  array<int, array{left: float, top: float, width: float, height: float}>  $boxes */
    private function faceBoxesMoved(array $boxes): bool
    {
        if (count($boxes) < 2) {
            return false;
        }

        $first = $this->boxCenter($boxes[0]);
        $last = $this->boxCenter($boxes[count($boxes) - 1]);
        $dx = abs($first['x'] - $last['x']);
        $dy = abs($first['y'] - $last['y']);
        $movement = sqrt($dx * $dx + $dy * $dy);

        return $movement >= 0.015;
    }

    /** @param  array{left: float, top: float, width: float, height: float}  $box */
    private function boxCenter(array $box): array
    {
        return [
            'x' => $box['left'] + ($box['width'] / 2),
            'y' => $box['top'] + ($box['height'] / 2),
        ];
    }

    /** @param  array<string, mixed>  $meta */
    private function issueToken(int $clientId, array $meta): string
    {
        $token = Str::random(64);
        $ttl = (int) config('authentiq.photo_verification_ttl_minutes', 10);

        Cache::put($this->cacheKey($clientId, $token), $meta, now()->addMinutes($ttl));

        return $token;
    }

    /** @return array<string, mixed>|null */
    private function consumeToken(int $clientId, string $token): ?array
    {
        $key = $this->cacheKey($clientId, $token);
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            return null;
        }
        Cache::forget($key);

        return $payload;
    }

    private function cacheKey(int $clientId, string $token): string
    {
        return 'client_photo_verify:'.$clientId.':'.$token;
    }
}
