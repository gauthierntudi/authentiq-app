<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommuneApiController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Commune::query()
            ->with(['ville.province'])
            ->orderBy('nom')
            ->get()
            ->map(fn (Commune $c) => [
                $c->id_commune,
                $c->nom,
                $c->id_ville,
                $c->ville?->nom,
                $c->ville?->province?->nom,
            ]);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_commune', 0);

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'ville' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tous les champs sont obligatoires',
            ]);
        }

        $data = $validator->validated();

        try {
            if ($id > 0) {
                Commune::query()->where('id_commune', $id)->update([
                    'nom' => $data['nom'],
                    'id_ville' => $data['ville'],
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Commune mise à jour !',
                ]);
            }

            Commune::query()->create([
                'nom' => $data['nom'],
                'id_ville' => $data['ville'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Commune ajoutée !',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur : '.$e->getMessage(),
            ]);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID commune invalide']);
        }

        try {
            Commune::query()->where('id_commune', $id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Commune supprimée !',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur : '.$e->getMessage(),
            ]);
        }
    }
}
