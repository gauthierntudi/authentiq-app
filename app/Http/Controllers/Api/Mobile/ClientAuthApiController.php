<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientAuthService;
use App\Services\ClientDuplicateGuard;
use App\Services\ClientKycService;
use App\Services\ClientPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * API mobile Flutter — inscription et connexion client (sans interface web).
 */
class ClientAuthApiController extends Controller
{
    public function __construct(
        private ClientAuthService $clientAuth,
        private ClientDuplicateGuard $duplicateGuard,
        private ClientPhotoStorage $clientPhotos,
        private ClientKycService $kyc,
    ) {}

    /** Inscription autonome : crée le client (inactif) et envoie l'OTP. */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom_complet' => 'required|string|max:255',
            'tel' => ['required', 'regex:/^0\d{9}$/'],
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'id_province' => 'required|integer|min:1',
            'id_ville' => 'required|integer|min:1',
            'type_piece_identite' => 'required|string|in:CNI,Passeport',
            'numero_national' => 'required_if:type_piece_identite,CNI|nullable|string|max:50',
            'numero_passeport' => 'required_if:type_piece_identite,Passeport|nullable|string|max:50',
            'adresse' => 'nullable|string',
        ], [
            'tel.regex' => 'Numéro de téléphone invalide (10 chiffres, commence par 0).',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $exists = Client::query()
            ->where('tel', $data['tel'])
            ->orWhere(function ($q) use ($data) {
                if (! empty($data['email'])) {
                    $q->where('email', $data['email']);
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Un compte existe déjà avec ce téléphone ou cet email.',
            ], 409);
        }

        $client = Client::query()->create([
            'nom_complet' => $data['nom_complet'],
            'tel' => $data['tel'],
            'email' => $data['email'] ?? '',
            'id_province' => $data['id_province'],
            'id_ville' => $data['id_ville'],
            'type_piece_identite' => $data['type_piece_identite'] ?? '',
            'numero_national' => $data['numero_national'] ?? '',
            'numero_passeport' => $data['numero_passeport'] ?? '',
            'adresse' => $data['adresse'] ?? '',
            'is_active' => 0,
            'mobile_registered_at' => now(),
        ]);

        $this->clientAuth->setPassword($client, $data['password']);

        $delivery = $this->clientAuth->issueOtp($client);

        return response()->json([
            'status' => 'success',
            'message' => 'Compte créé. Validez le code OTP reçu.',
            'client_id' => $client->id_client,
            'otp_delivery' => [
                'whatsapp_sent' => $delivery['whatsapp_sent'] ?? false,
                'mail_sent' => $delivery['mail_sent'] ?? false,
            ],
        ], 201);
    }

    /**
     * Étape 1 connexion : vérifie le mot de passe puis envoie l'OTP (pas de token tant que l'OTP n'est pas validé).
     */
    public function login(Request $request): JsonResponse
    {
        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');

        if ($login === '' || $password === '') {
            return response()->json(['status' => 'error', 'message' => 'Identifiants requis.'], 422);
        }

        $client = Client::query()
            ->where('tel', $login)
            ->orWhere('email', $login)
            ->first();

        if (! $client || ! $this->clientAuth->verifyPassword($client, $password)) {
            return response()->json(['status' => 'error', 'message' => 'Identifiants incorrects.'], 401);
        }

        $delivery = $this->clientAuth->issueOtp($client);

        $channels = [];
        if ($delivery['whatsapp_sent'] ?? false) {
            $channels[] = 'WhatsApp';
        }
        if ($delivery['mail_sent'] ?? false) {
            $channels[] = 'email';
        }

        $message = 'Identifiants valides. Saisissez le code OTP pour continuer.';
        if ($channels !== []) {
            $message .= ' Code envoyé par '.implode(' et ', $channels).'.';
        }

        return response()->json([
            'status' => 'success',
            'requires_otp' => true,
            'client_id' => $client->id_client,
            'tel' => $client->tel,
            'message' => $message,
            'otp_delivery' => [
                'whatsapp_sent' => $delivery['whatsapp_sent'] ?? false,
                'mail_sent' => $delivery['mail_sent'] ?? false,
            ],
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $clientId = (int) $request->input('client_id', 0);
        $tel = trim((string) $request->input('tel', ''));

        $client = $clientId > 0
            ? Client::query()->find($clientId)
            : Client::query()->where('tel', $tel)->first();

        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable.'], 404);
        }

        $delivery = $this->clientAuth->issueOtp($client);

        return response()->json([
            'status' => 'success',
            'client_id' => $client->id_client,
            'message' => 'Code OTP envoyé.',
            'otp_delivery' => $delivery,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $clientId = (int) $request->input('client_id', 0);
        $code = trim((string) $request->input('code_otp', ''));

        if ($clientId <= 0 || $code === '') {
            return response()->json(['status' => 'error', 'message' => 'client_id et code_otp requis.'], 422);
        }

        $client = $this->clientAuth->verifyOtp($clientId, $code);

        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'OTP invalide ou expiré.'], 401);
        }

        $token = $this->clientAuth->issueToken($client->fresh());

        return response()->json([
            'status' => 'success',
            'message' => 'Compte activé.',
            'client' => $this->clientPayload($client),
            'auth' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $client = \App\Support\CurrentClient::get();
        if ($client) {
            $this->clientAuth->revokeAllTokens($client);
        }

        return response()->json(['status' => 'success', 'message' => 'Déconnecté.']);
    }

    public function me(): JsonResponse
    {
        $client = \App\Support\CurrentClient::get();

        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Non authentifié.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'client' => $this->clientPayload($client->load(['province', 'ville'])),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $client = \App\Support\CurrentClient::get();

        if (! $this->kyc->canEditProfile($client)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil verrouillé : votre identité est validée (KYC approuvé).',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nom_complet' => 'sometimes|required|string|max:255',
            'tel' => ['sometimes', 'required', 'regex:/^0\d{9}$/'],
            'email' => 'sometimes|required|email|max:255',
            'id_province' => 'sometimes|required|integer|min:1',
            'id_ville' => 'sometimes|required|integer|min:1',
            'type_piece_identite' => 'sometimes|required|string|in:CNI,Passeport',
            'numero_national' => 'nullable|string|max:50',
            'numero_passeport' => 'nullable|string|max:50',
            'adresse' => 'nullable|string',
        ], [
            'tel.regex' => 'Numéro de téléphone invalide (10 chiffres, commence par 0).',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if (isset($data['tel']) || isset($data['email'])) {
            $tel = $data['tel'] ?? $client->tel;
            $email = $data['email'] ?? $client->email;
            $exists = Client::query()
                ->where('id_client', '!=', $client->id_client)
                ->where(function ($q) use ($tel, $email) {
                    $q->where('tel', $tel);
                    if ($email !== '') {
                        $q->orWhere('email', $email);
                    }
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce téléphone ou cet e-mail est déjà utilisé.',
                ], 409);
            }
        }

        if (isset($data['type_piece_identite'])) {
            if ($data['type_piece_identite'] === 'CNI' && empty($data['numero_national'] ?? $client->numero_national)) {
                return response()->json(['status' => 'error', 'message' => 'Numéro national requis pour la CNI.'], 422);
            }
            if ($data['type_piece_identite'] === 'Passeport' && empty($data['numero_passeport'] ?? $client->numero_passeport)) {
                return response()->json(['status' => 'error', 'message' => 'Numéro de passeport requis.'], 422);
            }
        }

        $client->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil mis à jour.',
            'client' => $this->clientPayload($client->fresh()->load(['province', 'ville'])),
        ]);
    }

    /** Photo du client connecté (Bearer token requis). */
    public function photo(): Response
    {
        $client = \App\Support\CurrentClient::get();

        if (! $client || ! $client->photo) {
            return response('', 404);
        }

        $bytes = $this->clientPhotos->readBytes($client->photo);
        if ($bytes === null || $bytes === '') {
            return response('', 404);
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** @return array<string, mixed> */
    private function clientPayload(Client $client): array
    {
        return [
            'id_client' => $client->id_client,
            'nom_complet' => $client->nom_complet,
            'tel' => $client->tel,
            'email' => $client->email,
            'is_active' => (int) $client->is_active,
            'id_province' => $client->id_province,
            'id_ville' => $client->id_ville,
            'nom_province' => $client->province?->nom,
            'nom_ville' => $client->ville?->nom,
            'mobile_registered_at' => $client->mobile_registered_at?->toIso8601String(),
            'photo_url' => $this->mobilePhotoUrl($client),
            'type_piece_identite' => $client->type_piece_identite,
            'numero_national' => $client->numero_national,
            'numero_passeport' => $client->numero_passeport,
            'adresse' => $client->adresse,
            'kyc_status' => $this->kyc->resolveStatus($client),
            'can_edit_profile' => $this->kyc->canEditProfile($client),
            'kyc' => $this->kyc->kycPayload($client),
        ];
    }

    private function mobilePhotoUrl(Client $client): ?string
    {
        if (! $client->photo) {
            return null;
        }

        $v = $client->photoCacheVersion() ?? time();

        return url('/api/mobile/client/me/photo').'?v='.$v;
    }
}
