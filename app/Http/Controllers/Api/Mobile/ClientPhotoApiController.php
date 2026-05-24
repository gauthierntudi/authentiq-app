<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientPhotoStorage;
use App\Services\ClientPhotoVerificationService;
use App\Services\EncodageClientAssociationService;
use App\Services\RekognitionService;
use App\Support\CurrentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ClientPhotoApiController extends Controller
{
    public function __construct(
        private ClientPhotoVerificationService $verification,
        private ClientPhotoStorage $photos,
        private RekognitionService $rekognition,
        private EncodageClientAssociationService $clientAssociation,
    ) {}

    /** Photo d'un client co-titulaire (Bearer token, même document requis). */
    public function showClientPhoto(int $id): Response
    {
        $viewer = CurrentClient::get();
        if (! $viewer) {
            return response('', 401);
        }

        $client = Client::query()->find($id);
        if (! $client || ! $client->photo) {
            return response('', 404);
        }

        if ((int) $viewer->id_client !== $id
            && ! $this->clientAssociation->clientsShareDocument((int) $viewer->id_client, $id)) {
            return response('', 403);
        }

        return $this->photoResponse($client);
    }

  /** Démarre la vérification de présence (selfies natifs). */
    public function startVerification(): JsonResponse
    {
        $client = CurrentClient::get();
        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Non authentifié.'], 401);
        }

        $session = $this->verification->startVerificationSession($client);
        if ($session['mode'] === 'denied') {
            return response()->json(['status' => 'error', 'message' => $session['message']], 403);
        }
        if ($session['mode'] === 'disabled') {
            return response()->json(['status' => 'error', 'message' => $session['message']], 503);
        }

        return response()->json([
            'status' => 'success',
            'verification' => $session,
            'has_current_photo' => (bool) $client->photo,
            'rekognition_enabled' => $this->rekognition->enabled(),
        ]);
    }

  /** Selfies en direct (anti-photo imprimée) + comparaison avec photo actuelle. */
    public function verifyPresence(Request $request): JsonResponse
    {
        $client = CurrentClient::get();
        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Non authentifié.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'frames' => 'required|array|min:'.(int) config('authentiq.photo_presence_min_frames', 3),
            'frames.*' => 'required|image|max:5120',
        ], [
            'frames.required' => 'Envoyez plusieurs selfies en direct.',
            'frames.*.image' => 'Chaque capture doit être une image.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $frames */
        $frames = $request->file('frames', []);
        $result = $this->verification->verifyMultiCapturePresence($client, $frames);

        if (! $result['token']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'photo_verification_token' => $result['token'],
            'expires_in_minutes' => (int) config('authentiq.photo_verification_ttl_minutes', 10),
        ]);
    }

  /** Nouvelle photo profil après vérification + comparaison Rekognition. */
    public function updatePhoto(Request $request): JsonResponse
    {
        $client = CurrentClient::get();
        if (! $client) {
            return response()->json(['status' => 'error', 'message' => 'Non authentifié.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:5120',
            'photo_verification_token' => 'required|string|min:32|max:128',
        ], [
            'photo.required' => 'Photo requise.',
            'photo_verification_token.required' => 'Vérifiez d\'abord votre présence avant de changer la photo.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $token = (string) $request->input('photo_verification_token');
        $photo = $request->file('photo');
        $check = $this->verification->applyNewPhoto($client, $photo, $token);

        if (! $check['client']) {
            return response()->json(['status' => 'error', 'message' => $check['message']], 422);
        }

        $client = $check['client'];
        $photoBytes = file_get_contents($photo->getRealPath()) ?: null;
        $path = $this->photos->store($client, $photo);
        $client->update(['photo' => $path]);

        if ($this->rekognition->enabled() && $photoBytes) {
            $this->rekognition->indexClientFace($client->fresh(), $photoBytes);
        }

        $client = $client->fresh()->load(['province', 'ville']);

        $v = $client->photoCacheVersion() ?? time();
        $photoUrl = $client->photo
            ? url('/api/mobile/client/me/photo').'?v='.$v
            : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Photo de profil mise à jour.',
            'photo_url' => $photoUrl,
            'client_id' => $client->id_client,
        ]);
    }

    private function photoResponse(Client $client): Response
    {
        $bytes = $this->photos->readBytes($client->photo);
        if ($bytes === null || $bytes === '') {
            return response('', 404);
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
