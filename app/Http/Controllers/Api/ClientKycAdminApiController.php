<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientKycSubmission;
use App\Services\ClientKycService;
use App\Services\ClientKycStorage;
use App\Services\ClientPhotoStorage;
use App\Services\UserAccessService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientKycAdminApiController extends Controller
{
    public function __construct(
        private ClientKycService $kyc,
        private ClientKycStorage $kycStorage,
        private ClientPhotoStorage $clientPhotos,
        private UserAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = CurrentUser::get();
        if ($deny = $this->access->denyUnlessStaff($user)) {
            return $deny;
        }

        $status = $request->query('status');
        $clientIds = $this->access->scopeClients(Client::query(), $user)->pluck('id_client');

        $query = ClientKycSubmission::query()
            ->with(['client.province', 'client.ville'])
            ->whereIn('id_client', $clientIds)
            ->orderByDesc('id_submission');

        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $items = $query->limit(500)->get()->map(fn (ClientKycSubmission $s) => $this->submissionRow($s));

        $pendingCount = ClientKycSubmission::query()
            ->whereIn('id_client', $clientIds)
            ->where('status', ClientKycSubmission::STATUS_PENDING)
            ->count();

        return response()->json([
            'status' => 'success',
            'pending_count' => $pendingCount,
            'submissions' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = CurrentUser::get();
        if ($deny = $this->access->denyUnlessStaff($user)) {
            return $deny;
        }

        $submission = $this->findSubmissionForUser($user, $id);
        if (! $submission) {
            return response()->json(['status' => 'error', 'message' => 'Soumission introuvable.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'submission' => $this->submissionRow($submission, detailed: true),
        ]);
    }

    public function image(int $id, string $side): \Symfony\Component\HttpFoundation\Response
    {
        $user = CurrentUser::get();
        if (! $user || ! $this->access->isStaff($user)) {
            abort(403);
        }

        if (! in_array($side, ['recto', 'verso'], true)) {
            abort(404);
        }

        $submission = $this->findSubmissionForUser($user, $id);
        if (! $submission) {
            abort(404);
        }

        $path = $side === 'recto' ? $submission->recto_path : $submission->verso_path;
        if (! $path) {
            abort(404);
        }

        $bytes = $this->kycStorage->readBytes($path);
        if ($bytes === null || $bytes === '') {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        return $this->review($id, ClientKycSubmission::STATUS_APPROVED, null);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $reason = trim((string) $request->input('rejection_reason', ''));

        return $this->review($id, ClientKycSubmission::STATUS_REJECTED, $reason !== '' ? $reason : null);
    }

    private function review(int $id, string $status, ?string $reason): JsonResponse
    {
        $user = CurrentUser::get();
        if ($deny = $this->access->denyUnlessStaff($user)) {
            return $deny;
        }

        $submission = $this->findSubmissionForUser($user, $id);
        if (! $submission) {
            return response()->json(['status' => 'error', 'message' => 'Soumission introuvable.'], 404);
        }

        if ($submission->status !== ClientKycSubmission::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cette soumission a déjà été traitée.',
            ], 422);
        }

        $updated = $this->kyc->reviewSubmission($submission, $status, $reason);

        return response()->json([
            'status' => 'success',
            'message' => $status === ClientKycSubmission::STATUS_APPROVED
                ? 'KYC approuvé. Le client est notifié.'
                : 'KYC refusé. Le client peut renvoyer une demande.',
            'submission' => $this->submissionRow($updated->load(['client.province', 'client.ville']), detailed: true),
        ]);
    }

    private function findSubmissionForUser($user, int $id): ?ClientKycSubmission
    {
        $submission = ClientKycSubmission::query()
            ->with(['client.province', 'client.ville'])
            ->find($id);

        if (! $submission || ! $submission->client) {
            return null;
        }

        if (! $this->access->canAccessClient($user, $submission->client)) {
            return null;
        }

        return $submission;
    }

    /** @return array<string, mixed> */
    private function submissionRow(ClientKycSubmission $s, bool $detailed = false): array
    {
        $client = $s->client;
        $row = [
            'id_submission' => $s->id_submission,
            'id_client' => $s->id_client,
            'status' => $s->status,
            'type_piece_identite' => $s->type_piece_identite,
            'submitted_at' => $s->submitted_at?->toIso8601String(),
            'reviewed_at' => $s->reviewed_at?->toIso8601String(),
            'rejection_reason' => $s->rejection_reason,
            'client' => $client ? [
                'id_client' => $client->id_client,
                'nom_complet' => $client->nom_complet,
                'tel' => $client->tel,
                'email' => $client->email,
                'nom_ville' => $client->ville?->nom,
                'nom_province' => $client->province?->nom,
                'photo_url' => $this->clientPhotos->photoUrl(
                    $client->photo,
                    $client->id_client,
                    $client->photoCacheVersion(),
                ),
            ] : null,
        ];

        if ($detailed) {
            $row['recto_url'] = $s->recto_path
                ? url('/api/kyc-submissions/'.$s->id_submission.'/image/recto')
                : null;
            $row['verso_url'] = $s->verso_path
                ? url('/api/kyc-submissions/'.$s->id_submission.'/image/verso')
                : null;
            $row['has_verso'] = (bool) $s->verso_path;
        }

        return $row;
    }
}
