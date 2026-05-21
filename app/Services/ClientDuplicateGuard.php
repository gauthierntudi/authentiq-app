<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\UploadedFile;

class ClientDuplicateGuard
{
    public function __construct(
        private RekognitionService $rekognition,
        private ClientPhotoStorage $clientPhotos,
    ) {}

    /**
     * @return array{
     *   allowed: bool,
     *   conflict: array{field: string, message: string, similarity?: float}|null,
     *   client: array<string, mixed>|null
     * }
     */
    public function check(
        string $tel,
        string $email,
        ?string $typePieceIdentite,
        ?string $numeroPiece,
        ?string $numeroNational,
        ?string $numeroPassport,
        ?string $photoBytes,
        int $excludeClientId = 0,
    ): array {
        $telNorm = $this->normalizeTel($tel);
        $emailNorm = $this->normalizeEmail($email);

        if ($telNorm !== '') {
            $existing = $this->findByTel($telNorm, $excludeClientId);
            if ($existing) {
                return $this->deny('tel', 'Ce numéro de téléphone est déjà associé à un client.', $existing);
            }
        }

        if ($emailNorm !== '') {
            $existing = $this->findByEmail($emailNorm, $excludeClientId);
            if ($existing) {
                return $this->deny('email', 'Cette adresse e-mail est déjà associée à un client.', $existing);
            }
        }

        $existingPiece = $this->findByPieceNumber(
            $typePieceIdentite,
            $numeroPiece,
            $numeroNational,
            $numeroPassport,
            $excludeClientId,
        );
        if ($existingPiece) {
            return $this->deny(
                'numero_piece',
                'Ce numéro de pièce d\'identité est déjà associé à un client.',
                $existingPiece,
            );
        }

        if ($photoBytes !== null && $photoBytes !== '') {
            $faceConflict = $this->checkFaceDuplicate($photoBytes, $excludeClientId);
            if ($faceConflict !== null) {
                return $faceConflict;
            }
        }

        return [
            'allowed' => true,
            'conflict' => null,
            'client' => null,
        ];
    }

    public function checkFromRequest(
        \Illuminate\Http\Request $request,
        int $excludeClientId = 0,
        bool $requirePhotoBytes = false,
    ): array {
        $photoBytes = $this->readPhotoBytesFromRequest($request);
        if ($requirePhotoBytes && ($photoBytes === null || $photoBytes === '')) {
            return [
                'allowed' => false,
                'conflict' => [
                    'field' => 'photo',
                    'message' => 'Photo requise pour vérifier les doublons.',
                ],
                'client' => null,
            ];
        }

        return $this->check(
            (string) $request->input('tel', $request->input('clientTel', '')),
            (string) $request->input('email', $request->input('clientEmail', '')),
            $request->input('type_piece_identite', $request->input('clientTypePiece')),
            (string) $request->input('clientNumeroPiece', ''),
            (string) $request->input('numero_national', ''),
            (string) $request->input('numero_passeport', ''),
            $photoBytes,
            $excludeClientId,
        );
    }

    /** @return array<string, mixed> */
    public function formatClientForApi(Client $client, ?float $similarity = null): array
    {
        $client->loadMissing(['province', 'ville']);

        $data = [
            'id_client' => $client->id_client,
            'nom_complet' => $client->nom_complet,
            'tel' => $client->tel,
            'email' => $client->email,
            'photo_url' => $this->clientPhotos->photoUrl(
                $client->photo,
                $client->id_client,
                $client->photoCacheVersion(),
            ),
            'is_active' => (int) $client->is_active,
            'type_piece_identite' => $client->type_piece_identite,
            'numero_national' => $client->numero_national,
            'numero_passeport' => $client->numero_passeport,
            'adresse' => $client->adresse,
            'nom_province' => $client->province?->nom,
            'nom_ville' => $client->ville?->nom,
        ];

        if ($similarity !== null) {
            $data['similarity'] = round($similarity, 1);
        }

        return $data;
    }

    private function readPhotoBytesFromRequest(\Illuminate\Http\Request $request): ?string
    {
        $file = $request->file('photo') ?? $request->file('clientPhoto');
        if ($file instanceof UploadedFile && $file->isValid()) {
            $bytes = file_get_contents($file->getRealPath());

            return $bytes !== false ? $bytes : null;
        }

        return null;
    }

    /**
     * @return array{allowed: bool, conflict: array|null, client: array|null}|null
     */
    private function checkFaceDuplicate(string $photoBytes, int $excludeClientId): ?array
    {
        if (! $this->rekognition->enabled()) {
            return null;
        }

        $result = $this->rekognition->searchClientByImageBytes($photoBytes);
        $client = $result['client'] ?? null;
        if (! $client instanceof Client) {
            return null;
        }

        if ($excludeClientId > 0 && (int) $client->id_client === $excludeClientId) {
            return null;
        }

        $similarity = (float) ($result['similarity'] ?? 0);
        $threshold = (float) config('authentiq.rekognition_min_similarity', 80);
        if ($similarity < $threshold) {
            return null;
        }

        return $this->deny(
            'photo',
            sprintf(
                'Ce visage correspond déjà à un client enregistré (%.1f %% de similarité, seuil %.0f %%).',
                $similarity,
                $threshold,
            ),
            $client,
            $similarity,
        );
    }

    private function findByTel(string $telNorm, int $excludeClientId): ?Client
    {
        $candidates = Client::query()
            ->when($excludeClientId > 0, fn ($q) => $q->where('id_client', '!=', $excludeClientId))
            ->whereNotNull('tel')
            ->where('tel', '!=', '')
            ->get(['id_client', 'tel', 'email', 'nom_complet', 'photo', 'type_piece_identite', 'numero_national', 'numero_passeport', 'adresse', 'is_active', 'id_province', 'id_ville']);

        foreach ($candidates as $client) {
            if ($this->normalizeTel((string) $client->tel) === $telNorm) {
                return $client;
            }
        }

        return null;
    }

    private function findByEmail(string $emailNorm, int $excludeClientId): ?Client
    {
        return Client::query()
            ->when($excludeClientId > 0, fn ($q) => $q->where('id_client', '!=', $excludeClientId))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->first();
    }

    private function findByPieceNumber(
        ?string $typePiece,
        ?string $numeroPiece,
        ?string $numeroNational,
        ?string $numeroPassport,
        int $excludeClientId,
    ): ?Client {
        $national = trim((string) $numeroNational);
        $passport = trim((string) $numeroPassport);
        $piece = trim((string) $numeroPiece);
        $type = trim((string) $typePiece);

        if ($piece !== '') {
            if ($type === 'CNI') {
                $national = $piece;
            } elseif ($type === 'Passeport') {
                $passport = $piece;
            } else {
                $national = $national !== '' ? $national : $piece;
                $passport = $passport !== '' ? $passport : $piece;
            }
        }

        if ($national === '' && $passport === '') {
            return null;
        }

        return Client::query()
            ->when($excludeClientId > 0, fn ($q) => $q->where('id_client', '!=', $excludeClientId))
            ->where(function ($q) use ($national, $passport) {
                if ($national !== '') {
                    $q->orWhere('numero_national', $national);
                }
                if ($passport !== '') {
                    $q->orWhere('numero_passeport', $passport);
                }
            })
            ->first();
    }

    private function normalizeTel(string $tel): string
    {
        $digits = preg_replace('/\D+/', '', trim($tel));

        return $digits !== '' ? $digits : trim($tel);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @return array{allowed: bool, conflict: array{field: string, message: string, similarity?: float}, client: array<string, mixed>}
     */
    private function deny(string $field, string $message, Client $client, ?float $similarity = null): array
    {
        $conflict = [
            'field' => $field,
            'message' => $message,
        ];
        if ($similarity !== null) {
            $conflict['similarity'] = round($similarity, 1);
        }

        return [
            'allowed' => false,
            'conflict' => $conflict,
            'client' => $this->formatClientForApi($client, $similarity),
        ];
    }
}
