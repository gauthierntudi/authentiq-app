<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Province;
use App\Models\Ville;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoApiController extends Controller
{
    public function provinces(): JsonResponse
    {
        $data = Province::query()->orderBy('nom')->get(['id_province', 'nom']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function villesByProvince(Request $request): JsonResponse
    {
        $id = (int) $request->query('id_province', 0);

        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Province invalide']);
        }

        $data = Ville::query()
            ->where('id_province', $id)
            ->orderBy('nom')
            ->get(['id_ville', 'nom as nom_ville']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function communesByVille(Request $request): JsonResponse
    {
        $id = (int) $request->query('id_ville', 0);

        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Ville invalide']);
        }

        $data = Commune::query()
            ->where('id_ville', $id)
            ->orderBy('nom')
            ->get(['id_commune', 'nom']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function villesForSelect(): JsonResponse
    {
        $data = Ville::query()
            ->with('province')
            ->orderBy('nom')
            ->get()
            ->map(fn (Ville $v) => [
                'id_ville' => $v->id_ville,
                'nom_ville' => $v->nom,
                'nom_province' => $v->province?->nom,
            ]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }
}
