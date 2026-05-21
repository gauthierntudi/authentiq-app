<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileApiController extends Controller
{
    public function show(): JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatProfile($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $sessionUser = CurrentUser::get();

        if (! $sessionUser) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $user = User::query()->findOrFail($sessionUser->id_user);

        $newPassword = $request->input('newPassword');
        $updatingInfo = $request->hasAny(['nom_complet', 'tel', 'email']);

        if ($updatingInfo) {
            $validator = Validator::make($request->all(), [
                'nom_complet' => 'required|string|max:255',
                'tel' => ['required', 'regex:/^0\d{9}$/'],
                'email' => 'required|email|max:255',
                'id_province' => 'nullable|integer',
                'id_ville' => 'nullable|integer',
                'id_commune' => 'nullable|integer',
                'affectation' => 'nullable|string|max:255',
                'photo' => 'nullable|image|max:5120',
            ], [
                'tel.regex' => 'Numéro de téléphone invalide',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ]);
            }

            $data = $validator->validated();

            $duplicate = User::query()
                ->where(function ($q) use ($data) {
                    $q->where('tel', $data['tel'])->orWhere('email', $data['email']);
                })
                ->where('id_user', '!=', $user->id_user)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Téléphone ou email déjà utilisé',
                ]);
            }

            $user->nom_complet = $data['nom_complet'];
            $user->tel = $data['tel'];
            $user->email = $data['email'];
            $user->id_province = $data['id_province'] ?: null;
            $user->id_ville = $data['id_ville'] ?: null;
            $user->id_commune = $data['id_commune'] ?: null;
            $user->affectation = $data['affectation'] ?? '';
        }

        if ($newPassword) {
            $confirm = $request->input('confirmPassword', $newPassword);
            if ($newPassword !== $confirm) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Les mots de passe ne correspondent pas.',
                ]);
            }

            $current = $request->input('currentPassword', '');
            if (! password_verify($current, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mot de passe actuel incorrect.',
                ]);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if ($request->hasFile('photo')) {
            $user->photo = $this->storePhoto($request->file('photo'));
        }

        try {
            $user->save();
            $user->load(['province', 'ville', 'commune']);
            CurrentUser::loginFromModel($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Profil mis à jour avec succès.',
                'data' => $this->formatProfile($user),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function formatProfile(User $u): array
    {
        $photo = $u->photo ? ltrim(str_replace('../', '', $u->photo), '/') : null;

        return [
            'id_user' => $u->id_user,
            'nom_complet' => $u->nom_complet,
            'tel' => $u->tel,
            'email' => $u->email,
            'role' => $u->role,
            'affectation' => $u->affectation,
            'photo' => $photo,
            'photo_url' => $photo ? asset($photo) : asset('assets/images/user.jpg'),
            'id_province' => $u->id_province,
            'id_ville' => $u->id_ville,
            'id_commune' => $u->id_commune,
            'nom_province' => $u->province?->nom,
            'nom_ville' => $u->ville?->nom,
            'nom_commune' => $u->commune?->nom,
        ];
    }

    private function storePhoto($file): string
    {
        $dir = public_path('uploads/users');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'user_'.Str::random(12).'.jpg';
        $file->move($dir, $name);

        return 'uploads/users/'.$name;
    }
}
