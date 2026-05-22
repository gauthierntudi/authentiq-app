<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailService;
use App\Services\UserPhotoStorage;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserApiController extends Controller
{
    public function __construct(
        private MailService $mail,
        private UserPhotoStorage $userPhotos,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()
            ->with(['province', 'ville', 'commune'])
            ->orderBy('nom_complet')
            ->get()
            ->map(fn (User $u) => $this->formatUser($u));

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function photo(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $current = CurrentUser::get();
        $user = User::query()->find($id);

        if (! $current || ! $user || ! $current->isStaff()) {
            return redirect(asset('assets/images/user.jpg'));
        }

        if (! $user->photo) {
            return redirect(asset('assets/images/user.jpg'));
        }

        $bytes = $this->userPhotos->readBytes($user->photo);
        if ($bytes === null || $bytes === '') {
            return redirect(asset('assets/images/user.jpg'));
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_user', 0);

        $validator = Validator::make($request->all(), [
            'nom_complet' => 'required|string|max:255',
            'tel' => ['required', 'regex:/^0\d{9}$/'],
            'email' => 'required|email|max:255',
            'password' => $id > 0 ? 'nullable|string|min:4' : 'nullable|string|min:4',
            'role' => 'required|in:admin,user',
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
            ->when($id > 0, fn ($q) => $q->where('id_user', '!=', $id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Téléphone ou email déjà utilisé',
            ]);
        }

        try {
            if ($id > 0) {
                $user = User::query()->findOrFail($id);
                $user->nom_complet = $data['nom_complet'];
                $user->tel = $data['tel'];
                $user->email = $data['email'];
                $user->role = $data['role'];
                $user->id_province = $data['id_province'] ?: null;
                $user->id_ville = $data['id_ville'] ?: null;
                $user->id_commune = $data['id_commune'] ?: null;
                $user->affectation = $data['affectation'] ?? '';

                if (! empty($data['password'])) {
                    $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                if ($request->hasFile('photo')) {
                    $user->photo = $this->userPhotos->store($user, $request->file('photo'));
                }

                $user->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Utilisateur mis à jour.',
                ]);
            }

            $plainPassword = ! empty($data['password'])
                ? $data['password']
                : bin2hex(random_bytes(4));

            $user = User::query()->create([
                'nom_complet' => $data['nom_complet'],
                'tel' => $data['tel'],
                'email' => $data['email'],
                'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
                'role' => $data['role'],
                'id_province' => $data['id_province'] ?: null,
                'id_ville' => $data['id_ville'] ?: null,
                'id_commune' => $data['id_commune'] ?: null,
                'affectation' => $data['affectation'] ?? '',
                'photo' => null,
            ]);

            if ($request->hasFile('photo')) {
                $user->photo = $this->userPhotos->store($user, $request->file('photo'));
                $user->save();
            }

            $mailBody = $this->mail->renderTemplate('mail_new_user.html', [
                'NOM' => $data['nom_complet'],
                'EMAIL' => $data['email'],
                'PASSWORD' => $plainPassword,
                'ANNEE' => date('Y'),
            ]);

            $this->mail->send(
                $data['email'],
                $data['nom_complet'],
                'Vos identifiants de connexion',
                $mailBody
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Utilisateur ajouté et mail envoyé.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID utilisateur invalide',
            ]);
        }

        try {
            User::query()->where('id_user', $id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Utilisateur supprimé avec succès !',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function formatUser(User $u): array
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
            'photo_url' => $this->userPhotos->photoUrl($u->photo, $u->id_user, $u->photoCacheVersion()),
            'id_province' => $u->id_province,
            'id_ville' => $u->id_ville,
            'id_commune' => $u->id_commune,
            'nom_province' => $u->province?->nom,
            'nom_ville' => $u->ville?->nom,
            'nom_commune' => $u->commune?->nom,
        ];
    }
}
