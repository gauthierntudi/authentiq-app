<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DocumentVerifyGrant;
use App\Models\Encodage;

class DocumentVerifyAuthorizationService
{
    public function canClientViewEncodage(Client $viewer, Encodage $encodage): bool
    {
        if (! $encodage->id_client) {
            return false;
        }

        if ((int) $encodage->id_client === (int) $viewer->id_client) {
            return true;
        }

        return DocumentVerifyGrant::query()
            ->where('id_encodage', $encodage->id_encodage)
            ->where('id_client_grantee', $viewer->id_client)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function grant(
        Client $owner,
        Encodage $encodage,
        Client $grantee,
        ?\DateTimeInterface $expiresAt = null,
    ): DocumentVerifyGrant {
        if ((int) $encodage->id_client !== (int) $owner->id_client) {
            throw new \RuntimeException('Ce document ne vous appartient pas.');
        }

        DocumentVerifyGrant::query()
            ->where('id_encodage', $encodage->id_encodage)
            ->where('id_client_grantee', $grantee->id_client)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return DocumentVerifyGrant::query()->create([
            'id_encodage' => $encodage->id_encodage,
            'id_client_owner' => $owner->id_client,
            'id_client_grantee' => $grantee->id_client,
            'expires_at' => $expiresAt,
        ]);
    }

    public function revoke(Client $owner, int $grantId): bool
    {
        $grant = DocumentVerifyGrant::query()
            ->where('id_grant', $grantId)
            ->where('id_client_owner', $owner->id_client)
            ->first();

        if (! $grant) {
            return false;
        }

        $grant->update(['revoked_at' => now()]);

        return true;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, DocumentVerifyGrant> */
    public function listGrantsForOwner(Client $owner, ?int $encodageId = null)
    {
        return DocumentVerifyGrant::query()
            ->with(['grantee:id_client,nom_complet,tel', 'encodage:id_encodage,numero'])
            ->where('id_client_owner', $owner->id_client)
            ->when($encodageId, fn ($q) => $q->where('id_encodage', $encodageId))
            ->orderByDesc('id_grant')
            ->get();
    }
}
