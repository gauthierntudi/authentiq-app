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
}
