<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Encodage;
use App\Services\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicVerifyController extends Controller
{
    public function __construct(private DocumentStorage $storage) {}

    public function show(string $numero): View
    {
        $encodage = $this->findByNumero($numero);

        return view('pages.verify-document', [
            'encodage' => $encodage,
            'numero' => strtoupper($numero),
            'qrUrl' => $this->storage->url($encodage?->qr_path),
            'firstPageUrl' => $this->firstPageUrl($encodage),
        ]);
    }

    public function api(string $numero): JsonResponse
    {
        $encodage = $this->findByNumero($numero);

        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Document introuvable.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'document' => [
                'numero' => $encodage->numero,
                'statut' => $encodage->status,
                'type_doc' => $encodage->doc?->nom_doc ?: $encodage->type_doc,
                'date_emission' => $encodage->date_emission?->format('Y-m-d'),
                'date_expiration' => $encodage->date_expiration?->format('Y-m-d'),
                'affectation' => $encodage->affectation ?: $encodage->commune?->nom,
                'client' => $encodage->client?->nom_complet,
                'est_valide' => $encodage->status === 'complete',
                'est_expire' => $encodage->status === 'expired',
            ],
        ]);
    }

    private function findByNumero(string $numero): ?Encodage
    {
        $normalized = strtoupper(trim($numero));

        if ($normalized === '') {
            return null;
        }

        return Encodage::query()
            ->with(['client', 'doc', 'commune', 'pages'])
            ->where('numero', $normalized)
            ->whereIn('status', ['complete', 'expired'])
            ->first();
    }

    private function firstPageUrl(?Encodage $encodage): ?string
    {
        if (! $encodage) {
            return null;
        }

        $page = $encodage->pages->sortBy('page_number')->first();

        return $this->storage->url($page?->file_path);
    }
}
