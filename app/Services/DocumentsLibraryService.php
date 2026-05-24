<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentsLibraryService
{
    public function __construct(
        private DocumentStorage $storage,
        private ClientPhotoStorage $clientPhotos,
        private EncodageClientAssociationService $clientAssociation,
    ) {}

    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   clients: list<array<string, mixed>>,
     *   recent: list<array<string, mixed>>,
     *   encodages: list<array<string, mixed>>
     * }
     */
    public function browse(User $user, ?string $search = null, ?string $status = null, ?int $clientId = null): array
    {
        $query = $this->encodageQuery($user)
            ->select([
                'id_encodage', 'id_client', 'id_doc', 'id_user', 'type_doc', 'status',
                'numero', 'qr_path', 'page_count', 'affectation', 'created_at', 'updated_at',
            ])
            ->with([
                'client:'.Client::EAGER_SELECT,
                'associatedClients:'.Client::EAGER_SELECT,
                'doc:id_doc,nom_doc,type_doc,ownership',
                'pages' => fn ($q) => $q
                    ->select('id_page', 'id_encodage', 'page_number', 'file_path', 'file_size')
                    ->orderBy('page_number'),
            ])
            ->orderByDesc('updated_at')
            ->limit(300);

        if ($clientId !== null && $clientId > 0) {
            $query->where(function (Builder $q) use ($clientId) {
                $q->where('id_client', $clientId)
                    ->orWhereHas('associatedClients', fn (Builder $c) => $c->where('id_client', $clientId));
            });
        } elseif ($clientId === 0) {
            $query->whereNull('id_client');
        }

        if (in_array($status, ['complete', 'incomplete', 'expired'], true)) {
            $query->where('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('id_encodage', 'like', $term)
                    ->orWhere('affectation', 'like', $term)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('nom_complet', 'like', $term))
                    ->orWhereHas('associatedClients', fn (Builder $c) => $c->where('nom_complet', 'like', $term));
            });
        }

        $encodages = $query->get();
        $formatted = $encodages->map(fn (Encodage $e) => $this->formatEncodage($e))->values();

        $clients = $this->buildClientFolders($encodages, $clientId);
        $recent = $formatted->take(20)->values()->all();

        $totalBytes = (int) $formatted->sum('size_bytes');
        $totalPages = (int) $formatted->sum('pages_count');

        return [
            'summary' => [
                'encodages_count' => $formatted->count(),
                'clients_count' => count($clients),
                'pages_count' => $totalPages,
                'size_bytes' => $totalBytes,
                'size_label' => $this->formatBytes($totalBytes),
            ],
            'clients' => $clients,
            'recent' => $recent,
            'encodages' => $formatted->all(),
            'selected_client_id' => $clientId > 0 ? $clientId : null,
        ];
    }

    private function encodageQuery(User $user): Builder
    {
        $query = Encodage::query();

        if ($user->role !== 'admin') {
            $query->where('id_user', $user->id_user);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Encodage>  $encodages
     * @return list<array<string, mixed>>
     */
    private function buildClientFolders(Collection $encodages, ?int $selectedId): array
    {
        /** @var array<int, array{items: Collection<int, Encodage>, last_activity: int}> $folderMap */
        $folderMap = [];

        foreach ($encodages as $enc) {
            $clientIds = $this->clientAssociation->associatedClientIds($enc);
            if ($clientIds === [] && $enc->id_client) {
                $clientIds = [(int) $enc->id_client];
            }

            $activity = (int) ($enc->updated_at?->timestamp ?? 0);

            foreach ($clientIds as $idClient) {
                if (! isset($folderMap[$idClient])) {
                    $folderMap[$idClient] = [
                        'items' => collect(),
                        'last_activity' => 0,
                    ];
                }

                if (! $folderMap[$idClient]['items']->contains('id_encodage', $enc->id_encodage)) {
                    $folderMap[$idClient]['items']->push($enc);
                }

                $folderMap[$idClient]['last_activity'] = max($folderMap[$idClient]['last_activity'], $activity);
            }
        }

        if ($folderMap === []) {
            return [];
        }

        $clientsById = Client::query()
            ->whereIn('id_client', array_keys($folderMap))
            ->get(['id_client', 'nom_complet', 'photo', 'created_at'])
            ->keyBy('id_client');

        $folders = [];

        foreach ($folderMap as $idClient => $data) {
            $client = $clientsById->get($idClient);
            $items = $data['items'];

            $sizeBytes = 0;
            $pagesCount = 0;
            foreach ($items as $enc) {
                $pagesCount += (int) ($enc->page_count ?? $enc->pages->count());
                foreach ($enc->pages as $page) {
                    $sizeBytes += (int) ($page->file_size ?? 0);
                }
            }

            $folders[] = [
                'id_client' => (int) $idClient,
                'nom_complet' => $client?->nom_complet ?: 'Sans client',
                'photo_url' => $this->clientPhotos->photoUrl(
                    $client?->photo,
                    $idClient > 0 ? $idClient : null,
                    $client?->photoCacheVersion(),
                ),
                'encodages_count' => $items->count(),
                'pages_count' => $pagesCount,
                'size_bytes' => $sizeBytes,
                'size_label' => $this->formatBytes($sizeBytes),
                'folder_color' => $this->folderColor((int) $idClient),
                'is_selected' => $selectedId > 0 && (int) $idClient === $selectedId,
                'last_activity' => $data['last_activity'],
            ];
        }

        usort($folders, fn ($a, $b) => ($b['last_activity'] ?? 0) <=> ($a['last_activity'] ?? 0));

        foreach ($folders as &$f) {
            unset($f['last_activity']);
        }

        return $folders;
    }

    /** @return array<string, mixed> */
    private function formatEncodage(Encodage $e): array
    {
        $pages = $e->pages->sortBy('page_number');
        $firstPage = $pages->first();
        $sizeBytes = (int) $pages->sum(fn (EncodagePage $p) => (int) ($p->file_size ?? 0));
        $pagesCount = (int) ($e->page_count ?? $pages->count());

        $typeDoc = $e->doc?->nom_doc ?: $e->type_doc ?: 'Document';
        $ownership = $e->doc?->ownership ?? 'single';
        $associatedClients = $this->clientAssociation->clientsListForEncodage($e, $this->clientPhotos);
        $clientLabel = $this->clientAssociation->clientsDisplayLabel(
            $associatedClients,
            $e->client?->nom_complet ?: 'Sans client',
        );
        $primaryClient = $associatedClients[0] ?? null;

        return [
            'id_encodage' => $e->id_encodage,
            'id_client' => (int) ($e->id_client ?? 0),
            'ownership' => $ownership,
            'clients_count' => max(count($associatedClients), $e->client ? 1 : 0),
            'associated_clients' => $associatedClients,
            'client_nom' => $clientLabel,
            'client_photo_url' => $primaryClient['photo_url'] ?? $this->clientPhotos->photoUrl(
                $e->client?->photo,
                $e->client?->id_client,
                $e->client?->photoCacheVersion(),
            ),
            'type_doc' => $typeDoc,
            'status' => $e->status,
            'numero' => $e->numero,
            'pages_count' => $pagesCount,
            'size_bytes' => $sizeBytes,
            'size_label' => $this->formatBytes($sizeBytes),
            'preview_url' => $firstPage?->file_path
                ? $this->storage->urlForKnownPath($firstPage->file_path)
                : null,
            'updated_at' => $e->updated_at?->format('d M, H:i'),
            'updated_iso' => $e->updated_at?->toIso8601String(),
            'verify_url' => $e->numero ? url('/verify/'.$e->numero) : null,
            'qr_url' => $e->qr_path ? $this->storage->urlForKnownPath($e->qr_path) : null,
            'folder_color' => $this->folderColor((int) $e->id_client),
        ];
    }

    private function folderColor(int $id): string
    {
        return ['blue', 'purple', 'orange', 'rose', 'teal', 'indigo'][$id % 6];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' o';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' Ko';
        }

        return round($bytes / (1024 * 1024), 1).' Mo';
    }
}
