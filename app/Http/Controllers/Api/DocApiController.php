<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocApiController extends Controller
{
    public function index(): JsonResponse
    {
        $docs = Doc::query()
            ->orderBy('nom_doc')
            ->get(['id_doc', 'nom_doc', 'type_doc', 'montant', 'duree', 'validite']);

        return response()->json(['status' => 'success', 'data' => $docs]);
    }

    public function save(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_doc', 0);

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'type' => 'required|in:free,payant',
            'montant' => 'nullable|numeric|min:0',
            'duree' => 'nullable|integer|min:0',
            'validite' => 'required|in:court,moyen,long',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first() ?: 'Tous les champs obligatoires doivent être remplis',
            ]);
        }

        $data = $validator->validated();
        $duree = (int) ($data['duree'] ?? 0);
        if ($request->input('illimite') == '1') {
            $duree = 0;
        }

        try {
            $payload = [
                'nom_doc' => $data['nom'],
                'type_doc' => $data['type'],
                'montant' => $data['montant'] ?? 0,
                'duree' => $duree,
                'validite' => $data['validite'],
            ];

            if ($id > 0) {
                Doc::query()->where('id_doc', $id)->update($payload);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Document mis à jour avec succès !',
                ]);
            }

            Doc::query()->create($payload);

            return response()->json([
                'status' => 'success',
                'message' => 'Document ajouté avec succès !',
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
            return response()->json(['status' => 'error', 'message' => 'ID document invalide']);
        }

        try {
            Doc::query()->where('id_doc', $id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Document supprimé avec succès !',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur : '.$e->getMessage(),
            ]);
        }
    }
}
