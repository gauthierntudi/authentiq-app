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

    /**
     * Compare deux images (bytes) — même personne si similarité >= seuil.
     *
     * @return array{match: bool, similarity: float|null, message: string}
     */
    public function compareFacesBytes(string $sourceBytes, string $targetBytes): array
    {
        if (! $this->enabled()) {
            return [
                'match' => false,
                'similarity' => null,
                'message' => 'Reconnaissance faciale désactivée.',
            ];
        }

        $threshold = (float) config('authentiq.rekognition_min_similarity', 80);

        try {
            $result = $this->client()->compareFaces([
                'SourceImage' => ['Bytes' => $sourceBytes],
                'TargetImage' => ['Bytes' => $targetBytes],
                'SimilarityThreshold' => $threshold,
                'QualityFilter' => 'AUTO',
            ]);
        } catch (RekognitionException $e) {
            if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                return [
                    'match' => false,
                    'similarity' => null,
                    'message' => 'Visage non détecté sur une des photos. Utilisez un cadrage net du visage.',
                ];
            }

            return [
                'match' => false,
                'similarity' => null,
                'message' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ];
        }

        $matches = $result['FaceMatches'] ?? [];
        if ($matches === []) {
            return [
                'match' => false,
                'similarity' => 0.0,
                'message' => 'La personne sur la nouvelle photo ne correspond pas à la photo actuelle.',
            ];
        }

        $similarity = (float) ($matches[0]['Similarity'] ?? 0);

        return [
            'match' => $similarity >= $threshold,
            'similarity' => $similarity,
            'message' => $similarity >= $threshold
                ? 'Même personne confirmée.'
                : 'Similarité insuffisante entre les deux photos.',
        ];
    }

    /**
     * Détecte un visage et retourne la boîte englobante (anti-photo statique imprimée).
     *
     * @return array{ok: bool, box: array{left: float, top: float, width: float, height: float}|null, message: string}
     */
    public function detectPrimaryFaceBox(string $imageBytes): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'box' => null, 'message' => 'Reconnaissance faciale désactivée.'];
        }

        try {
            $result = $this->client()->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT'],
            ]);
        } catch (RekognitionException $e) {
            return ['ok' => false, 'box' => null, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        }

        $faces = $result['FaceDetails'] ?? [];
        if ($faces === []) {
            return ['ok' => false, 'box' => null, 'message' => 'Aucun visage détecté.'];
        }

        usort($faces, fn ($a, $b) => ($b['Confidence'] ?? 0) <=> ($a['Confidence'] ?? 0));
        $face = $faces[0];
        $bbox = $face['BoundingBox'] ?? null;
        if (! $bbox) {
            return ['ok' => false, 'box' => null, 'message' => 'Visage illisible.'];
        }

        $confidence = (float) ($face['Confidence'] ?? 0);
        if ($confidence < 90) {
            return ['ok' => false, 'box' => null, 'message' => 'Qualité du visage insuffisante. Rapprochez-vous et améliorez la lumière.'];
        }

        return [
            'ok' => true,
            'box' => [
                'left' => (float) ($bbox['Left'] ?? 0),
                'top' => (float) ($bbox['Top'] ?? 0),
                'width' => (float) ($bbox['Width'] ?? 0),
                'height' => (float) ($bbox['Height'] ?? 0),
            ],
            'message' => 'Visage détecté.',
        ];
    }

    public function faceLivenessConfigured(): bool
    {
        return $this->enabled()
            && (bool) config('authentiq.face_liveness_enabled', false)
            && (string) config('authentiq.face_liveness_s3_bucket') !== ''
            && (string) config('authentiq.cognito_identity_pool_id') !== '';
    }

    /**
     * @return array{session_id: string|null, message: string}
     */
    public function createFaceLivenessSession(): array
    {
        if (! $this->faceLivenessConfigured()) {
            return ['session_id' => null, 'message' => 'Face Liveness non configuré (S3 + Cognito Identity Pool).'];
        }

        $bucket = (string) config('authentiq.face_liveness_s3_bucket');
        $prefix = (string) config('authentiq.face_liveness_s3_prefix', 'face-liveness/');

        try {
            $result = $this->client()->createFaceLivenessSession([
                'Settings' => [
                    'OutputConfig' => [
                        'S3Bucket' => $bucket,
                        'S3KeyPrefix' => $prefix,
                    ],
                    'AuditImagesLimit' => 4,
                ],
            ]);
        } catch (RekognitionException $e) {
            Log::warning('Rekognition Face Liveness session failed', ['error' => $e->getMessage()]);

            return ['session_id' => null, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        }

        return [
            'session_id' => $result['SessionId'] ?? null,
            'message' => 'Session créée.',
        ];
    }

    /**
     * @return array{
     *   success: bool,
     *   confidence: float|null,
     *   reference_bytes: string|null,
     *   message: string
     * }
     */
    public function getFaceLivenessSessionResults(string $sessionId): array
    {
        if (! $this->enabled()) {
            return [
                'success' => false,
                'confidence' => null,
                'reference_bytes' => null,
                'message' => 'Reconnaissance faciale désactivée.',
            ];
        }

        try {
            $result = $this->client()->getFaceLivenessSessionResults([
                'SessionId' => $sessionId,
            ]);
        } catch (RekognitionException $e) {
            return [
                'success' => false,
                'confidence' => null,
                'reference_bytes' => null,
                'message' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ];
        }

        $status = $result['Status'] ?? '';
        $confidence = isset($result['Confidence']) ? (float) $result['Confidence'] : null;
        $minConfidence = (float) config('authentiq.face_liveness_min_confidence', 90);

        if ($status !== 'SUCCEEDED') {
            return [
                'success' => false,
                'confidence' => $confidence,
                'reference_bytes' => null,
                'message' => 'Vérification de présence échouée ou annulée.',
            ];
        }

        if ($confidence === null || $confidence < $minConfidence) {
            return [
                'success' => false,
                'confidence' => $confidence,
                'reference_bytes' => null,
                'message' => 'Confiance de présence insuffisante. Réessayez dans un endroit bien éclairé.',
            ];
        }

        $reference = $result['ReferenceImage'] ?? [];
        $bytes = $reference['Bytes'] ?? null;
        if (! $bytes && isset($reference['S3Object'])) {
            $bytes = $this->readS3ObjectBytes(
                (string) ($reference['S3Object']['Bucket'] ?? ''),
                (string) ($reference['S3Object']['Name'] ?? ''),
            );
        }

        if (! $bytes) {
            return [
                'success' => false,
                'confidence' => $confidence,
                'reference_bytes' => null,
                'message' => 'Image de référence Face Liveness introuvable.',
            ];
        }

        return [
            'success' => true,
            'confidence' => $confidence,
            'reference_bytes' => $bytes,
            'message' => 'Présence physique confirmée.',
        ];
    }

    private function readS3ObjectBytes(string $bucket, string $key): ?string
    {
        if ($bucket === '' || $key === '') {
            return null;
        }

        try {
            $s3 = \Illuminate\Support\Facades\Storage::disk('s3');
            if ($s3->exists($key)) {
                return $s3->get($key);
            }
        } catch (\Throwable $e) {
            Log::warning('Rekognition liveness S3 read failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function client(): RekognitionClient
    {
        return AwsClientFactory::rekognition();
    }
}
