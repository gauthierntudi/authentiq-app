<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encodage;
use App\Services\ClientPhotoStorage;
use App\Services\ClientPhotoStorage;
use App\Services\DocumentStorage;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EncodageApiController extends Controller
{
    public function __construct(
        private DocumentStorage $storage,
        private ClientPhotoStorage $clientPhotos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $query = Encodage::query()
            ->with(['client', 'doc', 'user', 'commune'])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $query->where('id_user', $user->id_user);
        }

        $status = $request->query('status', '');
        if (in_array($status, ['incomplete', 'complete', 'expired'], true)) {
            $query->where('status', $status);
        }

        $period = $request->query('period', 'all');
        $this->applyPeriodFilter($query, $period);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id_encodage', $search)
                    ->orWhere('affectation', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('nom_complet', 'like', "%{$search}%"));
            });
        }

        $communeId = (int) $request->query('id_commune', 0);
        if ($communeId > 0) {
            $query->where('id_commune', $communeId);
        }

        $rows = $query->get();

        $stats = [
            'total' => $rows->count(),
            'incomplete' => $rows->where('status', 'incomplete')->count(),
            'complete' => $rows->where('status', 'complete')->count(),
            'expired' => $rows->where('status', 'expired')->count(),
        ];

        $data = $rows->map(fn (Encodage $e) => $this->formatRow($e));

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'stats' => $stats,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $encodage = Encodage::query()->with('pages')->find($id);

        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable']);
        }

        if ($user->role !== 'admin' && (int) $encodage->id_user !== (int) $user->id_user) {
            return response()->json(['status' => 'error', 'message' => 'Accès refusé'], 403);
        }

        try {
            DB::transaction(function () use ($encodage) {
                foreach ($encodage->pages as $page) {
                    $this->storage->delete($page->file_path);
                }

                $this->storage->delete($encodage->qr_path);

                if ($encodage->files) {
                    foreach (explode(',', (string) $encodage->files) as $legacyPath) {
                        $this->storage->delete(trim($legacyPath));
                    }
                }

                $encodage->pages()->delete();
                $encodage->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Encodage supprimé avec succès.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function applyPeriodFilter($query, string $period): void
    {
        match ($period) {
            'today' => $query->whereDate('created_at', today()),
            '7days' => $query->where('updated_at', '>=', now()->subDays(7)),
            '30days' => $query->where('updated_at', '>=', now()->subDays(30)),
            'legacy' => $query->where(function ($q) {
                $q->where(function ($complete) {
                    $complete->where('status', 'complete')
                        ->where('updated_at', '>=', now()->subDay());
                })->orWhere(function ($incomplete) {
                    $incomplete->where('status', 'incomplete')
                        ->where('updated_at', '>=', now()->subDays(7));
                });
            }),
            default => null,
        };
    }

    private function formatRow(Encodage $e): array
    {
        $typeDoc = $e->doc?->nom_doc ?: $e->type_doc;

        return [
            'id_encodage' => $e->id_encodage,
            'status' => $e->status,
            'numero' => $e->numero,
            'client_nom' => $e->client?->nom_complet,
            'client_photo_url' => $this->clientPhotos->photoUrl($e->client?->photo),
            'type_doc' => $typeDoc,
            'nb_pages' => (int) ($e->page_count ?? 0),
            'affectation' => $e->affectation ?: $e->commune?->nom,
            'user_nom' => $e->user?->nom_complet,
            'date_creation' => $e->created_at?->format('Y-m-d H:i:s'),
            'date_modification' => $e->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

}
