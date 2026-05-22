<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientKycSubmission;
use Illuminate\Http\UploadedFile;

class ClientKycService
{
    public function __construct(
        private ClientKycStorage $storage,
        private ClientNotificationService $notifications,
    ) {}

    public function resolveStatus(Client $client): string
    {
        $latest = $this->latestSubmission($client);

        if (! $latest) {
            return 'none';
        }

        return $latest->status;
    }

    public function canEditProfile(Client $client): bool
    {
        return $this->resolveStatus($client) !== ClientKycSubmission::STATUS_APPROVED;
    }

    public function latestSubmission(Client $client): ?ClientKycSubmission
    {
        return ClientKycSubmission::query()
            ->where('id_client', $client->id_client)
            ->orderByDesc('id_submission')
            ->first();
    }

    public function kycPayload(Client $client): array
    {
        $latest = $this->latestSubmission($client);
        $status = $this->resolveStatus($client);

        return [
            'status' => $status,
            'can_edit_profile' => $this->canEditProfile($client),
            'latest_submission' => $latest ? [
                'id_submission' => $latest->id_submission,
                'status' => $latest->status,
                'type_piece_identite' => $latest->type_piece_identite,
                'rejection_reason' => $latest->rejection_reason,
                'submitted_at' => $latest->submitted_at?->toIso8601String(),
                'reviewed_at' => $latest->reviewed_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function submit(
        Client $client,
        UploadedFile $recto,
        ?UploadedFile $verso = null,
    ): ClientKycSubmission {
        $pending = ClientKycSubmission::query()
            ->where('id_client', $client->id_client)
            ->where('status', ClientKycSubmission::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw new \RuntimeException('Une vérification KYC est déjà en cours.');
        }

        if ($this->resolveStatus($client) === ClientKycSubmission::STATUS_APPROVED) {
            throw new \RuntimeException('Votre identité est déjà validée.');
        }

        $type = $client->type_piece_identite ?: 'CNI';
        if ($type === 'CNI' && $verso === null) {
            throw new \RuntimeException('La photo du verso de la CNI est requise.');
        }

        $submission = ClientKycSubmission::query()->create([
            'id_client' => $client->id_client,
            'status' => ClientKycSubmission::STATUS_PENDING,
            'type_piece_identite' => $type,
            'submitted_at' => now(),
        ]);

        $rectoPath = $this->storage->store(
            $client->id_client,
            $submission->id_submission,
            $recto,
            'recto',
        );
        $versoPath = $verso
            ? $this->storage->store($client->id_client, $submission->id_submission, $verso, 'verso')
            : null;

        $submission->update([
            'recto_path' => $rectoPath,
            'verso_path' => $versoPath,
        ]);

        $this->notifications->notify(
            $client,
            'kyc',
            'KYC en cours de vérification',
            'Vos photos de pièce d\'identité ont été reçues. Nous vous informerons dès validation.',
        );

        return $submission->fresh();
    }

    /** Validation manuelle (back-office / artisan) — notifie le client. */
    public function reviewSubmission(
        ClientKycSubmission $submission,
        string $status,
        ?string $rejectionReason = null,
    ): ClientKycSubmission {
        if (! in_array($status, [ClientKycSubmission::STATUS_APPROVED, ClientKycSubmission::STATUS_REJECTED], true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        $submission->update([
            'status' => $status,
            'rejection_reason' => $status === ClientKycSubmission::STATUS_REJECTED ? $rejectionReason : null,
            'reviewed_at' => now(),
        ]);

        $client = $submission->client ?? Client::query()->find($submission->id_client);
        if ($client) {
            if ($status === ClientKycSubmission::STATUS_APPROVED) {
                $this->notifications->notify(
                    $client,
                    'kyc',
                    'Identité validée',
                    'Votre pièce d\'identité a été approuvée. Votre profil est maintenant verrouillé.',
                );
            } else {
                $this->notifications->notify(
                    $client,
                    'kyc',
                    'KYC refusé',
                    $rejectionReason ?: 'Veuillez renvoyer des photos plus lisibles.',
                );
            }
        }

        return $submission->fresh();
    }
}
