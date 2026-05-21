<?php

namespace App\Services;

use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentsLibraryService
{
    public function __construct(private DocumentStorage $storage) {}

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
            ->with(['client', 'doc', 'pages'])
            ->orderByDesc('updated_at');

        if ($clientId !== null && $clientId > 0) {
            $query->where('id_client', $clientId);
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
                    ->orWhereHas('client', fn (Builder $c) => $c->where('nom_complet', 'like', $term));
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
        $byClient = $encodages->groupBy('id_client');
        $folders = [];

        foreach ($byClient as $idClient => $items) {
            $idClient = (int) $idClient;
            $client = $items->first()?->client;

            $sizeBytes = 0;
            $pagesCount = 0;
            foreach ($items as $enc) {
                $pagesCount += (int) ($enc->page_count ?? $enc->pages->count());
                foreach ($enc->pages as $page) {
                    $sizeBytes += (int) ($page->file_size ?? 0);
                }
            }

            $photo = $client?->photo
                ? ltrim(str_replace('../', '', $client->photo), '/')
                : null;

            $folders[] = [
                'id_client' => $idClient,
                'nom_complet' => $client?->nom_complet ?: 'Sans client',
                'photo_url' => $photo ? asset($photo) : asset('assets/images/user.jpg'),
                'encodages_count' => $items->count(),
                'pages_count' => $pagesCount,
                'size_bytes' => $sizeBytes,
                'size_label' => $this->formatBytes($sizeBytes),
                'folder_color' => $this->folderColor((int) $idClient),
                'is_selected' => $selectedId > 0 && (int) $idClient === $selectedId,
                'last_activity' => $items->max(fn (Encodage $e) => $e->updated_at?->timestamp ?? 0),
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

        $clientPhoto = $e->client?->photo
            ? ltrim(str_replace('../', '', $e->client->photo), '/')
            : null;

        $typeDoc = $e->doc?->nom_doc ?: $e->type_doc ?: 'Document';

        return [
            'id_encodage' => $e->id_encodage,
            'id_client' => (int) ($e->id_client ?? 0),
            'client_nom' => $e->client?->nom_complet ?: 'Sans client',
            'client_photo_url' => $clientPhoto
                ? asset($clientPhoto)
                : asset('assets/images/user.jpg'),
            'type_doc' => $typeDoc,
            'status' => $e->status,
            'numero' => $e->numero,
            'pages_count' => $pagesCount,
            'size_bytes' => $sizeBytes,
            'size_label' => $this->formatBytes($sizeBytes),
            'preview_url' => $firstPage?->file_path
                ? $this->storage->url($firstPage->file_path)
                : null,
            'updated_at' => $e->updated_at?->format('d M, H:i'),
            'updated_iso' => $e->updated_at?->toIso8601String(),
            'verify_url' => $e->numero ? url('/verify/'.$e->numero) : null,
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
