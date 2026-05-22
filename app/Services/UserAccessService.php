<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Encodage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class UserAccessService
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public function isAdmin(?User $user): bool
    {
        return $user && $user->role === self::ROLE_ADMIN;
    }

    public function isStaff(?User $user): bool
    {
        return $user && in_array($user->role, [self::ROLE_ADMIN, self::ROLE_USER], true);
    }

    public function denyUnlessAdmin(?User $user, string $message = 'Accès réservé aux administrateurs.'): ?JsonResponse
    {
        if ($this->isAdmin($user)) {
            return null;
        }

        return response()->json(['status' => 'error', 'message' => $message], 403);
    }

    public function denyUnlessStaff(?User $user): ?JsonResponse
    {
        if ($this->isStaff($user)) {
            return null;
        }

        return response()->json(['status' => 'error', 'message' => 'Accès non autorisé.'], 403);
    }

    public function scopeClients(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query
            ->where('id_province', $user->id_province)
            ->where('id_ville', $user->id_ville);
    }

    public function scopeEncodages(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->where('id_user', $user->id_user);
    }

    public function canAccessClient(User $user, Client $client): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return (int) $client->id_province === (int) $user->id_province
            && (int) $client->id_ville === (int) $user->id_ville;
    }

    public function canAccessEncodage(User $user, Encodage $encodage): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return (int) $encodage->id_user === (int) $user->id_user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enforceClientGeoForUser(User $user, array $data, bool $isNew): array
    {
        if ($this->isAdmin($user)) {
            return $data;
        }

        if (! $user->id_province || ! $user->id_ville) {
            throw new \RuntimeException('Votre compte agent n\'a pas de province/ville d\'affectation.');
        }

        if (! $isNew) {
            $requestedProvince = (int) ($data['id_province'] ?? 0);
            $requestedVille = (int) ($data['id_ville'] ?? 0);
            if ($requestedProvince > 0 && $requestedProvince !== (int) $user->id_province) {
                throw new \RuntimeException('Vous ne pouvez gérer que les clients de votre province.');
            }
            if ($requestedVille > 0 && $requestedVille !== (int) $user->id_ville) {
                throw new \RuntimeException('Vous ne pouvez gérer que les clients de votre ville.');
            }
        }

        $data['id_province'] = $user->id_province;
        $data['id_ville'] = $user->id_ville;

        return $data;
    }
}
