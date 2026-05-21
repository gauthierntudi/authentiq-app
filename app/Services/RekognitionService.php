<?php

namespace App\Services;

use App\Models\Client;
use App\Services\Aws\AwsClientFactory;
use Aws\Rekognition\Exception\RekognitionException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;

class RekognitionService
{
    public function enabled(): bool
    {
        return (bool) config('authentiq.rekognition_enabled', false) && AwsClientFactory::configured();
    }

    public function collectionId(): string
    {
        return (string) config('authentiq.rekognition_collection_id', 'authentiq-clients');
    }

    public function ensureCollection(): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->client()->createCollection([
                'CollectionId' => $this->collectionId(),
            ]);
        } catch (RekognitionException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }
        }
    }

    /**
     * Indexe le visage du client à partir de sa photo en base (fichier local).
     */
    public function indexClientFromStoredPhoto(Client $client): bool
    {
        $bytes = $this->readClientPhotoBytes($client);

        if (! $bytes) {
            Log::warning('Rekognition: photo client introuvable sur le disque', [
                'client_id' => $client->id_client,
                'photo' => $client->photo,
            ]);

            return false;
        }

        return $this->indexClientFace($client, $bytes);
    }

    public function indexClientFace(Client $client, string $imageBytes): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $this->ensureCollection();
        $this->deleteClientFaces($client->id_client);

        $result = $this->client()->indexFaces([
            'CollectionId' => $this->collectionId(),
            'Image' => ['Bytes' => $imageBytes],
            'ExternalImageId' => (string) $client->id_client,
            'MaxFaces' => 1,
            'QualityFilter' => 'AUTO',
        ]);

        $indexed = $result['FaceRecords'] ?? [];
        if ($indexed === []) {
            $reasons = collect($result['UnindexedFaces'] ?? [])
                ->pluck('Reasons')
                ->flatten()
                ->unique()
                ->values()
                ->all();

            Log::warning('Rekognition: aucun visage indexé pour le client', [
                'client_id' => $client->id_client,
                'reasons' => $reasons,
            ]);

            return false;
        }

        Log::info('Rekognition: visage indexé', [
            'client_id' => $client->id_client,
            'face_id' => $indexed[0]['Face']['FaceId'] ?? null,
        ]);

        return true;
    }

    public function deleteClientFaces(int $clientId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $externalId = (string) $clientId;
        $nextToken = null;

        do {
            $params = [
                'CollectionId' => $this->collectionId(),
                'MaxResults' => 1000,
            ];
            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $result = $this->client()->listFaces($params);
            $faceIds = [];

            foreach ($result['Faces'] ?? [] as $face) {
                if (($face['ExternalImageId'] ?? '') === $externalId) {
                    $faceIds[] = $face['FaceId'];
                }
            }

            if ($faceIds !== []) {
                $this->client()->deleteFaces([
                    'CollectionId' => $this->collectionId(),
                    'FaceIds' => $faceIds,
                ]);
            }

            $nextToken = $result['NextToken'] ?? null;
        } while ($nextToken);
    }

    public function collectionHasFaces(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $this->ensureCollection();
        $result = $this->client()->listFaces([
            'CollectionId' => $this->collectionId(),
            'MaxResults' => 1,
        ]);

        return count($result['Faces'] ?? []) > 0;
    }

    public function countIndexedFaces(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $this->ensureCollection();
        $total = 0;
        $nextToken = null;

        do {
            $params = [
                'CollectionId' => $this->collectionId(),
                'MaxResults' => 1000,
            ];
            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $result = $this->client()->listFaces($params);
            $total += count($result['Faces'] ?? []);
            $nextToken = $result['NextToken'] ?? null;
        } while ($nextToken);

        return $total;
    }

    /**
     * @return array{client: Client|null, similarity: float|null, message: string}
     */
    public function searchClientByImageBytes(string $imageBytes): array
    {
        if (! $this->enabled()) {
            return [
                'client' => null,
                'similarity' => null,
                'message' => 'Reconnaissance faciale désactivée.',
            ];
        }

        $this->ensureCollection();

        if (! $this->collectionHasFaces()) {
            return [
                'client' => null,
                'similarity' => null,
                'message' => 'Aucun profil facial enregistré. Mettez à jour la photo du client dans la gestion clients.',
            ];
        }

        try {
            $result = $this->client()->searchFacesByImage([
                'CollectionId' => $this->collectionId(),
                'Image' => ['Bytes' => $imageBytes],
                'MaxFaces' => 3,
                'FaceMatchThreshold' => (float) config('authentiq.rekognition_min_similarity', 80),
                'QualityFilter' => 'AUTO',
            ]);
        } catch (RekognitionException $e) {
            $code = $e->getAwsErrorCode();
            if ($code === 'InvalidParameterException') {
                return [
                    'client' => null,
                    'similarity' => null,
                    'message' => 'Aucun visage détecté sur la photo capturée. Rapprochez-vous et réessayez.',
                ];
            }

            return [
                'client' => null,
                'similarity' => null,
                'message' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ];
        }

        $matches = $result['FaceMatches'] ?? [];
        if ($matches === []) {
            return [
                'client' => null,
                'similarity' => null,
                'message' => 'Aucun client reconnu (visage non correspondant).',
            ];
        }

        $match = $matches[0];
        $similarity = (float) ($match['Similarity'] ?? 0);
        $externalId = $match['Face']['ExternalImageId'] ?? null;

        if (! $externalId) {
            return [
                'client' => null,
                'similarity' => $similarity,
                'message' => 'Visage détecté sans identifiant client.',
            ];
        }

        $client = Client::query()->find((int) $externalId);

        if (! $client) {
            return [
                'client' => null,
                'similarity' => $similarity,
                'message' => 'Client lié au visage introuvable en base.',
            ];
        }

        return [
            'client' => $client,
            'similarity' => $similarity,
            'message' => 'Client reconnu.',
        ];
    }

    public function readClientPhotoBytes(Client $client): ?string
    {
        return app(ClientPhotoStorage::class)->readBytes($client->photo);
    }

    private function client(): RekognitionClient
    {
        return AwsClientFactory::rekognition();
    }
}
