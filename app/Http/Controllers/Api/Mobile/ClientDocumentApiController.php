<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Encodage;
use App\Services\DocumentStorage;
use App\Services\DocumentVerifyAuthorizationService;
use App\Services\EncodageClientAssociationService;
use App\Support\CurrentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API mobile Flutter — documents du client, vérification QR, autorisations tierces.
 */
class ClientDocumentApiController extends Controller
{
    public function __construct(
        private DocumentVerifyAuthorizationService $verifyAuth,
        private DocumentStorage $storage,
        private EncodageClientAssociationService $clientAssociation,
    ) {}

    /** Liste des documents encodés pour le client connecté. */
    public function index(): JsonResponse
    {
        $client = CurrentClient::get();

        $clientId = (int) $client->id_client;

        $items = Encodage::query()
            ->with([
                'doc',
                'commune',
                'pages' => fn ($q) => $q
                    ->select('id_page', 'id_encodage', 'page_number', 'file_path')
                    ->orderBy('page_number')
                    ->limit(1),
            ])
            ->whereIn('status', ['complete', 'expired'])
            ->where(function ($q) use ($clientId) {
                $q->where('id_client', $clientId)
                    ->orWhereHas('associatedClients', fn ($q2) => $q2->where('CLIENTS.id_client', $clientId));
            })
            ->orderByDesc('id_encodage')
            ->limit(200)
            ->get()
            ->map(function (Encodage $e) use ($client) {
                $payload = $this->encodagePayload($e, $client);
                $page = $e->relationLoaded('pages')
                    ? $e->pages->sortBy('page_number')->first()
                    : null;
                $payload['first_page_url'] = $this->storage->url($page?->file_path);

                return $payload;
            });

        return response()->json(['status' => 'success', 'documents' => $items]);
    }

    /** Documents qu'un autre client a partagés avec moi (autorisations actives). */
    public function sharedWithMe(): JsonResponse
    {
        $client = CurrentClient::get();

        $items = $this->verifyAuth->listEncodagesSharedWith($client)
            ->map(function (Encodage $e) {
                $payload = $this->encodagePayload($e, $client, detailed: true);
                $payload['is_owner'] = false;
                $payload['owner_nom'] = $e->client?->nom_complet;

                return $payload;
            });

        return response()->json(['status' => 'success', 'documents' => $items]);
    }

    /**
     * Vérifier un document via son numéro (scan QR).
     * Propriétaire : accès complet. Tiers : nécessite une autorisation accordée.
     */
    public function verify(Request $request): JsonResponse
    {
        $client = CurrentClient::get();
        $numero = strtoupper(trim((string) $request->input('numero', '')));

        if ($numero === '') {
            return response()->json(['status' => 'error', 'message' => 'Numéro de document requis.'], 422);
        }

        $encodage = Encodage::query()
            ->with(['client', 'doc', 'commune', 'pages'])
            ->where('numero', $numero)
            ->whereIn('status', ['complete', 'expired'])
            ->first();

        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Document introuvable.'], 404);
        }

        if (! $this->verifyAuth->canClientViewEncodage($client, $encodage)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous n\'êtes pas autorisé à consulter ce document. Demandez l\'autorisation au propriétaire.',
                'need_authorization' => true,
                'owner_hint' => $encodage->client?->nom_complet,
            ], 403);
        }

        $isOwner = $this->clientAssociation->isClientAssociated($encodage, (int) $client->id_client);
        $isPrimaryOwner = $this->clientAssociation->isPrimaryOwner($encodage, (int) $client->id_client);

        return response()->json([
            'status' => 'success',
            'is_owner' => $isOwner,
            'is_primary_owner' => $isPrimaryOwner,
            'document' => $this->encodagePayload($encodage, $client, detailed: true),
        ]);
    }

    public function listGrants(Request $request): JsonResponse
    {
        $owner = CurrentClient::get();
        $encodageId = $request->query('encodage_id') ? (int) $request->query('encodage_id') : null;

        $grants = $this->verifyAuth->listGrantsForOwner($owner, $encodageId);

        return response()->json([
            'status' => 'success',
            'grants' => $grants->map(fn ($g) => [
                'id_grant' => $g->id_grant,
                'id_encodage' => $g->id_encodage,
                'numero' => $g->encodage?->numero,
                'grantee' => [
                    'id_client' => $g->grantee?->id_client,
                    'nom_complet' => $g->grantee?->nom_complet,
                    'tel' => $g->grantee?->tel,
                ],
                'expires_at' => $g->expires_at?->toIso8601String(),
                'is_active' => $g->isActive(),
                'created_at' => $g->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** Accorder à un autre client le droit de scanner le QR de ce document. */
    public function grant(Request $request): JsonResponse
    {
        $owner = CurrentClient::get();

        $validator = Validator::make($request->all(), [
            'id_encodage' => 'required|integer|min:1',
            'grantee_tel' => 'nullable|regex:/^0\d{9}$/',
            'grantee_id_client' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $encodage = Encodage::query()->find((int) $data['id_encodage']);

        if (! $encodage || ! $this->clientAssociation->isPrimaryOwner($encodage, (int) $owner->id_client)) {
            return response()->json(['status' => 'error', 'message' => 'Document introuvable ou non autorisé.'], 404);
        }

        $grantee = null;
        if (! empty($data['grantee_id_client'])) {
            $grantee = Client::query()->find((int) $data['grantee_id_client']);
        } elseif (! empty($data['grantee_tel'])) {
            $grantee = Client::query()->where('tel', $data['grantee_tel'])->first();
        }

        if (! $grantee) {
            return response()->json(['status' => 'error', 'message' => 'Client bénéficiaire introuvable.'], 404);
        }

        if ((int) $grantee->id_client === (int) $owner->id_client) {
            return response()->json(['status' => 'error', 'message' => 'Vous ne pouvez pas vous autoriser vous-même.'], 422);
        }

        try {
            $expires = isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null;
            $grant = $this->verifyAuth->grant($owner, $encodage, $grantee, $expires);

            return response()->json([
                'status' => 'success',
                'message' => 'Autorisation accordée.',
                'grant' => [
                    'id_grant' => $grant->id_grant,
                    'id_encodage' => $grant->id_encodage,
                    'grantee_id_client' => $grant->id_client_grantee,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    public function revokeGrant(int $grantId): JsonResponse
    {
        $owner = CurrentClient::get();

        if (! $this->verifyAuth->revoke($owner, $grantId)) {
            return response()->json(['status' => 'error', 'message' => 'Autorisation introuvable.'], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Autorisation révoquée.']);
    }

    /** @return array<string, mixed> */
    private function encodagePayload(Encodage $encodage, ?Client $viewer = null, bool $detailed = false): array
    {
        $page = $encodage->relationLoaded('pages')
            ? $encodage->pages->sortBy('page_number')->first()
            : null;

        $payload = [
            'id_encodage' => $encodage->id_encodage,
            'numero' => $encodage->numero,
            'statut' => $encodage->status,
            'type_doc' => $encodage->doc?->nom_doc ?: $encodage->type_doc,
            'date_emission' => $encodage->date_emission?->format('Y-m-d'),
            'date_expiration' => $encodage->date_expiration?->format('Y-m-d'),
            'est_valide' => $encodage->status === 'complete',
            'est_expire' => $encodage->status === 'expired',
            'verify_url' => $encodage->numero ? url('/verify/'.$encodage->numero) : null,
            'qr_url' => $this->storage->url($encodage->qr_path),
        ];

        if ($viewer) {
            $payload['is_owner'] = $this->clientAssociation->isClientAssociated($encodage, (int) $viewer->id_client);
            $payload['is_primary_owner'] = $this->clientAssociation->isPrimaryOwner($encodage, (int) $viewer->id_client);
        }

        if ($detailed) {
            $payload['affectation'] = $encodage->affectation ?: $encodage->commune?->nom;
            $payload['client'] = $encodage->client?->nom_complet;
            $payload['first_page_url'] = $this->storage->url($page?->file_path);
        }

        return $payload;
    }
}
