<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ville;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VilleApiController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Ville::query()
            ->with('province')
            ->orderBy('nom')
            ->get()
            ->map(fn (Ville $v) => [
                $v->id_ville,
                $v->nom,
                $v->province?->nom,
                $v->id_province,
            ]);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_ville', 0);
        $nom = trim((string) ($request->input('nom') ?: $request->input('nomVille', '')));

        $validator = Validator::make([
            'nom' => $nom,
            'province' => $request->input('province'),
        ], [
            'nom' => 'required|string|max:255',
            'province' => 'required|integer|min:1',
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
                Ville::query()->where('id_ville', $id)->update([
                    'nom' => $data['nom'],
                    'id_province' => $data['province'],
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Ville mise à jour avec succès !',
                ]);
            }

            Ville::query()->create([
                'nom' => $data['nom'],
                'id_province' => $data['province'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Ville ajoutée avec succès !',
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
            return response()->json(['status' => 'error', 'message' => 'ID ville invalide']);
        }

        try {
            Ville::query()->where('id_ville', $id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Ville supprimée avec succès !',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur : '.$e->getMessage(),
            ]);
        }
    }
}
