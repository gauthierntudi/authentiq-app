<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientDuplicateGuard;
use App\Services\ClientOnboardingService;
use App\Services\ClientPhotoStorage;
use App\Services\OtpService;
use App\Services\RekognitionService;
use App\Services\UserAccessService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientApiController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private ClientOnboardingService $clientOnboarding,
        private RekognitionService $rekognition,
        private ClientPhotoStorage $clientPhotos,
        private ClientDuplicateGuard $duplicateGuard,
        private UserAccessService $access,
    ) {}

    public function checkDuplicates(Request $request): JsonResponse
    {
        $excludeId = (int) $request->input('id_client', 0);
        $isNew = $excludeId <= 0;

        $check = $this->duplicateGuard->checkFromRequest(
            $request,
            $excludeId,
            requirePhotoBytes: $isNew && ! $request->hasFile('photo'),
        );

        return response()->json([
            'status' => 'success',
            'allowed' => $check['allowed'],
            'conflict' => $check['conflict'],
            'client' => $check['client'],
            'message' => $check['allowed']
                ? null
                : ($check['conflict']['message'] ?? 'Doublon client détecté.'),
        ]);
    }

    public function index(): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $clients = $this->access->scopeClients(Client::query(), $user)
            ->with(['province', 'ville'])
            ->orderBy('nom_complet')
            ->get()
            ->map(fn (Client $c) => [
                'id_client' => $c->id_client,
                'nom_complet' => $c->nom_complet,
                'tel' => $c->tel,
                'email' => $c->email,
                'id_province' => $c->id_province,
                'id_ville' => $c->id_ville,
                'nom_province' => $c->province?->nom,
                'nom_ville' => $c->ville?->nom,
                'active' => (int) $c->is_active,
                'photo' => $c->photo ? ltrim(str_replace('../', '', $c->photo), '/') : null,
                'photo_url' => $this->clientPhotos->photoUrl(
                    $c->photo,
                    $c->id_client,
                    $c->photoCacheVersion(),
                ),
                'updated_at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['status' => 'success', 'data' => $clients]);
    }

    public function photo(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $user = CurrentUser::get();
        $client = Client::query()->find($id);

        if (! $client || ! $user || ! $this->access->canAccessClient($user, $client)) {
            return redirect(asset('assets/images/user.jpg'));
        }

        if (! $client->photo) {
            return redirect(asset('assets/images/user.jpg'));
        }

        $bytes = $this->clientPhotos->readBytes($client->photo);
        if ($bytes === null || $bytes === '') {
            return redirect(asset('assets/images/user.jpg'));
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = CurrentUser::get();
        $client = Client::query()
            ->with(['province', 'ville'])
            ->find($id);

        if (! $client || ! $user || ! $this->access->canAccessClient($user, $client)) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_client' => $client->id_client,
                'nom_complet' => $client->nom_complet,
                'tel' => $client->tel,
                'email' => $client->email,
                'photo' => $client->photo,
                'photo_url' => $this->clientPhotos->photoUrl(
                    $client->photo,
                    $client->id_client,
                    $client->photoCacheVersion(),
                ),
                'type_piece_identite' => $client->type_piece_identite,
                'numero_national' => $client->numero_national,
                'numero_passeport' => $client->numero_passeport,
                'adresse' => $client->adresse,
                'is_active' => (int) $client->is_active,
                'created_at' => $client->created_at,
                'nom_province' => $client->province?->nom,
                'nom_ville' => $client->ville?->nom,
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $staff = CurrentUser::get();
        if (! $staff) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $id = (int) $request->input('id_client', 0);

        $validator = Validator::make($request->all(), [
            'nom_complet' => 'required|string|max:255',
            'tel' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'id_province' => 'nullable|integer',
            'id_ville' => 'nullable|integer',
            'type_piece_identite' => 'nullable|string|max:50',
            'numero_national' => 'nullable|string|max:50',
            'numero_passeport' => 'nullable|string|max:50',
            'adresse' => 'nullable|string',
            'photo' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()]);
        }

        $data = $validator->validated();

        if ($id <= 0 && ! $request->hasFile('photo')) {
            return response()->json([
                'status' => 'error',
                'message' => 'La photo du client est obligatoire (capture via la caméra).',
            ]);
        }

        $duplicateCheck = $this->duplicateGuard->checkFromRequest(
            $request,
            $id,
            requirePhotoBytes: false,
        );
        if (! $duplicateCheck['allowed']) {
            return $this->duplicateResponse($duplicateCheck);
        }

        try {
            $data = $this->access->enforceClientGeoForUser($staff, $data, $isNew = $id <= 0);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        try {
            if ($id > 0) {
                $client = Client::query()->find($id);
                if (! $client || ! $this->access->canAccessClient($staff, $client)) {
                    return response()->json(['status' => 'error', 'message' => 'Client introuvable'], 404);
                }

                if (! $request->hasFile('photo') && ! $client->photo) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La photo du client est obligatoire (capture via la caméra).',
                    ]);
                }

                $updateData = [
                    'nom_complet' => $data['nom_complet'],
                    'tel' => $data['tel'] ?? '',
                    'email' => $data['email'] ?? '',
                    'id_province' => $data['id_province'] ?: null,
                    'id_ville' => $data['id_ville'] ?: null,
                    'type_piece_identite' => $data['type_piece_identite'] ?? '',
                    'numero_national' => $data['numero_national'] ?? '',
                    'numero_passeport' => $data['numero_passeport'] ?? '',
                    'adresse' => $data['adresse'] ?? '',
                ];

                $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;
                $photoBytes = $photoFile ? $this->clientPhotos->bytesFromUpload($photoFile) : null;
                if ($photoFile) {
                    $updateData['photo'] = $this->clientPhotos->store($client, $photoFile);
                }

                $client->update($updateData);

                if ($photoFile) {
                    $this->indexClientFaceNow($client->fresh(), $photoBytes);
                }

                $client = $client->fresh();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Client mis à jour.',
                    'client_id' => $id,
                    'photo_url' => $this->clientPhotos->photoUrl(
                        $client->photo,
                        $client->id_client,
                        $client->photoCacheVersion(),
                    ),
                ]);
            }

            $client = Client::query()->create([
                'nom_complet' => $data['nom_complet'],
                'tel' => $data['tel'] ?? '',
                'email' => $data['email'] ?? '',
                'id_province' => $data['id_province'] ?: null,
                'id_ville' => $data['id_ville'] ?: null,
                'type_piece_identite' => $data['type_piece_identite'] ?? '',
                'numero_national' => $data['numero_national'] ?? '',
                'numero_passeport' => $data['numero_passeport'] ?? '',
                'adresse' => $data['adresse'] ?? '',
                'is_active' => 0,
            ]);

            if ($request->hasFile('photo')) {
                $client->update([
                    'photo' => $this->clientPhotos->store($client, $request->file('photo')),
                ]);
            }

            $delivery = $this->clientOnboarding->onboardStaffCreatedClient($client->fresh());

            $message = $this->deliveryMessage('Client ajouté.', $delivery);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'client_id' => $client->id_client,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur serveur: '.$e->getMessage()]);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_client', 0);
        $code = trim((string) $request->input('code_otp', ''));

        if (! $id || $code === '') {
            return response()->json(['status' => 'error', 'message' => 'ID ou OTP manquant']);
        }

        if ($this->otpService->verifyClientOtp($id, $code)) {
            $client = Client::query()->find($id);
            if ($client) {
                $this->indexClientFaceNow($client);
            }

            return response()->json(['status' => 'success', 'message' => 'OTP validé et client activé']);
        }

        return response()->json(['status' => 'error', 'message' => 'Code OTP incorrect ou expiré']);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $id = (int) $request->input('id_client', 0);

        if (! $id) {
            return response()->json(['status' => 'error', 'message' => 'ID client manquant']);
        }

        $client = Client::query()->find($id);

        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable']);
        }

        $delivery = $this->otpService->issueForClient($client);

        $message = $this->deliveryMessage('Nouveau code OTP généré', $delivery);

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function resendCredentials(Request $request): JsonResponse
    {
        $staff = CurrentUser::get();
        if (! $staff) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $id = (int) $request->input('id_client', 0);
        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID client manquant']);
        }

        $client = Client::query()->find($id);
        if (! $client || ! $this->access->canAccessClient($staff, $client)) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable'], 404);
        }

        if (empty($client->email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ce client n\'a pas d\'adresse email — impossible d\'envoyer les identifiants par mail.',
            ]);
        }

        try {
            $delivery = $this->clientOnboarding->resendAccessCredentials($client);

            return response()->json([
                'status' => 'success',
                'message' => $this->deliveryMessage('Identifiants régénérés.', $delivery),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'envoi : '.$e->getMessage(),
            ]);
        }
    }

    public function searchByPhoto(Request $request): JsonResponse
    {
        $staff = CurrentUser::get();
        if (! $staff) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        if (! $this->rekognition->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Recherche par reconnaissance faciale non activée.',
            ], 503);
        }

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $bytes = file_get_contents($request->file('photo')->getRealPath());
        if ($bytes === false) {
            return response()->json(['status' => 'error', 'message' => 'Image illisible.']);
        }

        $result = $this->rekognition->searchClientByImageBytes($bytes);

        if (! $result['client']) {
            return response()->json([
                'status' => 'success',
                'found' => false,
                'message' => $result['message'],
            ]);
        }

        $client = $result['client']->load(['province', 'ville']);

        if (! $this->access->canAccessClient($staff, $client)) {
            return response()->json([
                'status' => 'success',
                'found' => false,
                'message' => 'Aucun client correspondant dans votre zone géographique.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'found' => true,
            'similarity' => $result['similarity'],
            'message' => $result['message'],
            'client' => [
                'id_client' => $client->id_client,
                'nom_complet' => $client->nom_complet,
                'tel' => $client->tel,
                'email' => $client->email,
                'photo_url' => $this->clientPhotos->photoUrl(
                    $client->photo,
                    $client->id_client,
                    $client->photoCacheVersion(),
                ),
                'is_active' => (int) $client->is_active,
                'type_piece_identite' => $client->type_piece_identite,
                'numero_national' => $client->numero_national,
                'numero_passeport' => $client->numero_passeport,
                'adresse' => $client->adresse,
                'nom_province' => $client->province?->nom,
                'nom_ville' => $client->ville?->nom,
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $staff = CurrentUser::get();
        $id = (int) $request->input('id_client', 0);
        $active = (int) $request->input('active', 0);

        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Client invalide']);
        }

        $client = Client::query()->find($id);
        if (! $client || ! $staff || ! $this->access->canAccessClient($staff, $client)) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable'], 404);
        }

        try {
            $client->update(['is_active' => $active]);

            return response()->json([
                'status' => 'success',
                'message' => $active ? 'Client activé' : 'Client désactivé',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /** @param  array{allowed: bool, conflict: ?array, client: ?array}  $check */
    private function duplicateResponse(array $check): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'duplicate' => true,
            'message' => $check['conflict']['message'] ?? 'Un client existant correspond déjà à ces informations.',
            'conflict' => $check['conflict'],
            'client' => $check['client'],
        ], 409);
    }

    /** @param  array{whatsapp_sent?: bool, mail_sent?: bool, credentials_mail_sent?: bool}  $delivery */
    private function deliveryMessage(string $prefix, array $delivery): string
    {
        $message = $prefix;
        if ($delivery['whatsapp_sent'] ?? false) {
            $message .= ' OTP envoyé par WhatsApp.';
        }
        if ($delivery['credentials_mail_sent'] ?? false) {
            $message .= ' Identifiants de connexion et code OTP envoyés par email.';
        } elseif ($delivery['mail_sent'] ?? false) {
            $message .= ' Code OTP envoyé par email.';
        } elseif (! ($delivery['whatsapp_sent'] ?? false)) {
            $message .= ' Aucun envoi automatique (vérifiez email/téléphone et la configuration mail/Twilio).';
        }

        return $message;
    }

    private function indexClientFaceNow(?Client $client, ?string $photoBytes = null): void
    {
        if (! $client || ! $this->rekognition->enabled()) {
            return;
        }

        try {
            if ($photoBytes !== null && $photoBytes !== '') {
                $this->rekognition->indexClientFace($client, $photoBytes);
            } else {
                $this->rekognition->indexClientFromStoredPhoto($client);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Rekognition index sync failed', [
                'client_id' => $client->id_client,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
