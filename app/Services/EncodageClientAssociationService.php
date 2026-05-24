<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Doc;
use App\Models\Encodage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EncodageClientAssociationService
{
    public const OWNERSHIP_SINGLE = 'single';

    public const OWNERSHIP_MULTIPLE = 'multiple';

    /** @return list<int> */
    public function associatedClientIds(Encodage $encodage): array
    {
        $pivotIds = DB::table('ENCODAGE_CLIENTS')
            ->where('id_encodage', $encodage->id_encodage)
            ->orderBy('id')
            ->pluck('id_client')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pivotIds !== []) {
            return array_values(array_unique($pivotIds));
        }

        if ($encodage->id_client) {
            return [(int) $encodage->id_client];
        }

        return [];
    }

    /**
     * @param  list<int>  $clientIds
     */
    public function syncClients(Encodage $encodage, array $clientIds, ?User $user = null): void
    {
        $clientIds = array_values(array_unique(array_filter(array_map('intval', $clientIds), fn ($id) => $id > 0)));

        if ($clientIds === []) {
            $encodage->update(['id_client' => null]);
            DB::table('ENCODAGE_CLIENTS')->where('id_encodage', $encodage->id_encodage)->delete();

            return;
        }

        if ($user && $user->role !== 'admin') {
            $allowed = Client::query()
                ->whereIn('id_client', $clientIds)
                ->where('id_province', $user->id_province)
                ->where('id_ville', $user->id_ville)
                ->pluck('id_client')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($allowed) !== count($clientIds)) {
                throw new \RuntimeException('Un ou plusieurs clients ne sont pas autorisés pour votre zone.');
            }
        }

        $primaryId = $clientIds[0];
        $encodage->update(['id_client' => $primaryId]);

        DB::table('ENCODAGE_CLIENTS')->where('id_encodage', $encodage->id_encodage)->delete();

        $now = now();
        $rows = array_map(fn (int $id) => [
            'id_encodage' => $encodage->id_encodage,
            'id_client' => $id,
            'created_at' => $now,
        ], $clientIds);

        DB::table('ENCODAGE_CLIENTS')->insert($rows);
    }

    public function validateForDoc(Encodage $encodage): ?string
    {
        if (! $encodage->id_doc) {
            return null;
        }

        $doc = Doc::query()->find($encodage->id_doc);
        if (! $doc) {
            return 'Type de document introuvable.';
        }

        $count = count($this->associatedClientIds($encodage));

        if ($count === 0) {
            return 'Associez au moins un client à cet encodage.';
        }

        if ($doc->ownership === self::OWNERSHIP_SINGLE && $count > 1) {
            return 'Ce type de document n\'accepte qu\'un seul client (propriété single).';
        }

        return null;
    }

    public function validateForFinalize(Encodage $encodage): ?string
    {
        $message = $this->validateForDoc($encodage);
        if ($message !== null) {
            return $message;
        }

        if (! $encodage->id_doc) {
            return null;
        }

        $doc = Doc::query()->find($encodage->id_doc);
        if (! $doc) {
            return 'Type de document introuvable.';
        }

        $count = count($this->associatedClientIds($encodage));

        if ($doc->ownership === self::OWNERSHIP_MULTIPLE && $count < 2) {
            return 'Ce type de document requiert au moins deux clients associés (propriété multiple).';
        }

        return null;
    }

    public function isClientAssociated(Encodage $encodage, int $clientId): bool
    {
        return in_array($clientId, $this->associatedClientIds($encodage), true);
    }

    public function isPrimaryOwner(Encodage $encodage, int $clientId): bool
    {
        return (int) $encodage->id_client === $clientId;
    }

    /** Les deux clients apparaissent sur au moins un même document finalisé. */
    public function clientsShareDocument(int $clientIdA, int $clientIdB): bool
    {
        if ($clientIdA === $clientIdB) {
            return true;
        }

        return Encodage::query()
            ->whereIn('status', ['complete', 'expired'])
            ->where(function ($q) use ($clientIdA) {
                $q->where('id_client', $clientIdA)
                    ->orWhereHas('associatedClients', fn ($q2) => $q2->where('CLIENTS.id_client', $clientIdA));
            })
            ->where(function ($q) use ($clientIdB) {
                $q->where('id_client', $clientIdB)
                    ->orWhereHas('associatedClients', fn ($q2) => $q2->where('CLIENTS.id_client', $clientIdB));
            })
            ->exists();
    }

    /**
     * @return list<array{id_client: int, nom_complet: string, photo_url: string|null, is_primary: bool}>
     */
    public function clientsListForEncodage(
        Encodage $encodage,
        ClientPhotoStorage $photos,
        bool $forMobile = false,
    ): array {
        $ids = $this->associatedClientIds($encodage);
        if ($ids === []) {
            return [];
        }

        if ($encodage->relationLoaded('associatedClients') && $encodage->associatedClients->isNotEmpty()) {
            $byId = $encodage->associatedClients->keyBy('id_client');
        } else {
            $byId = Client::query()
                ->whereIn('id_client', $ids)
                ->get(['id_client', 'nom_complet', 'photo', 'created_at'])
                ->keyBy('id_client');
        }

        $payload = [];
        foreach ($ids as $id) {
            $client = $byId->get($id);
            if (! $client) {
                continue;
            }

            $payload[] = [
                'id_client' => (int) $client->id_client,
                'nom_complet' => $client->nom_complet,
                'photo_url' => $this->clientPhotoUrl($client, $photos, $forMobile),
                'is_primary' => $this->isPrimaryOwner($encodage, (int) $client->id_client),
            ];
        }

        return $payload;
    }

    private function clientPhotoUrl(Client $client, ClientPhotoStorage $photos, bool $forMobile): ?string
    {
        if (! $client->photo) {
            return $forMobile ? null : $photos->defaultUrl();
        }

        if ($forMobile) {
            $v = $client->photoCacheVersion() ?? time();

            return url('/api/mobile/client/clients/'.$client->id_client.'/photo').'?v='.$v;
        }

        return $photos->photoUrl(
            $client->photo,
            $client->id_client,
            $client->photoCacheVersion(),
        );
    }

    /**
     * @param  list<array{nom_complet?: string|null}>  $clients
     */
    public function clientsDisplayLabel(array $clients, ?string $fallback = null): string
    {
        if ($clients === []) {
            return $fallback ?: '—';
        }

        if (count($clients) === 1) {
            return (string) ($clients[0]['nom_complet'] ?? $fallback ?? '—');
        }

        $names = array_values(array_filter(array_map(
            fn (array $c) => trim((string) ($c['nom_complet'] ?? '')),
            $clients,
        )));

        if ($names === []) {
            return $fallback ?: '—';
        }

        $visible = array_slice($names, 0, 2);
        $label = implode(', ', $visible);

        if (count($names) > 2) {
            $label .= ' +'.(count($names) - 2);
        }

        return $label;
    }
}
