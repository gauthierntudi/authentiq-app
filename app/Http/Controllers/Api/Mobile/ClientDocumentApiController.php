<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Services\ClientPhotoStorage;
use App\Services\DocumentStorage;
use App\Services\DocumentVerifyAuthorizationService;
use App\Services\EncodageClientAssociationService;
use App\Support\CurrentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * API mobile Flutter — documents du client, vérification QR, autorisations tierces.
 */
class ClientDocumentApiController extends Controller
{
    public function __construct(
        private DocumentVerifyAuthorizationService $verifyAuth,
        private DocumentStorage $storage,
        private EncodageClientAssociationService $clientAssociation,
        private ClientPhotoStorage $clientPhotos,
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
                'client:'.Client::EAGER_SELECT,
                'associatedClients:'.Client::EAGER_SELECT,
            ])
            ->whereIn('status', ['complete', 'expired'])
            ->where(function ($q) use ($clientId) {
                $q->where('id_client', $clientId)
                    ->orWhereHas('associatedClients', fn ($q2) => $q2->where('CLIENTS.id_client', $clientId));
            })
            ->orderByDesc('id_encodage')
            ->limit(200)
            ->get()
            ->map(fn (Encodage $e) => $this->documentListPayload($e, $client));

        return response()->json(['status' => 'success', 'documents' => $items]);
    }

    /** Détail d'un document + pages (consultation autorisée uniquement). */
    public function show(int $id): JsonResponse
    {
        $client = CurrentClient::get();

        $encodage = Encodage::query()
            ->with([
                'doc',
                'commune',
                'pages' => fn ($q) => $q->orderBy('page_number'),
                'associatedClients:'.Client::EAGER_SELECT,
            ])
            ->whereIn('status', ['complete', 'expired'])
            ->find($id);

        if (! $encodage || ! $this->verifyAuth->canClientViewEncodage($client, $encodage)) {
            return response()->json(['status' => 'error', 'message' => 'Document introuvable.'], 404);
        }

        $payload = $this->documentListPayload($encodage, $client, detailed: true);
        $payload['pages'] = $encodage->pages
            ->sortBy('page_number')
            ->values()
            ->map(fn ($page) => [
                'id_page' => $page->id_page,
                'page_number' => $page->page_number,
                'url' => $this->pageFilePath($id, (int) $page->id_page),
            ])
            ->all();

        return response()->json(['status' => 'success', 'document' => $payload]);
    }

    /** Image d'une page (proxy authentifié — évite URLs S3 / uploads directes). */
    public function pageFile(int $id, int $pageId): Response|JsonResponse
    {
        $client = CurrentClient::get();

        $encodage = Encodage::query()
            ->whereIn('status', ['complete', 'expired'])
            ->find($id);

        if (! $encodage || ! $this->verifyAuth->canClientViewEncodage($client, $encodage)) {
            return response()->json(['status' => 'error', 'message' => 'Document introuvable.'], 404);
        }

        $page = EncodagePage::query()
            ->where('id_encodage', $id)
            ->where('id_page', $pageId)
            ->first();

        if (! $page || ! $page->file_path) {
            return response()->json(['status' => 'error', 'message' => 'Page non trouvée.'], 404);
        }

        $bytes = $this->storage->readBytes($page->file_path);
        if ($bytes === null || $bytes === '') {
            return response()->json(['status' => 'error', 'message' => 'Fichier page introuvable.'], 404);
        }

        $ext = Str::lower(pathinfo($page->file_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Documents qu'un autre client a partagés avec moi (autorisations actives). */
    public function sharedWithMe(): JsonResponse
    {
        $client = CurrentClient::get();

        $items = $this->verifyAuth->listEncodagesSharedWith($client)
            ->map(function (Encodage $e) use ($client) {
                $payload = $this->documentListPayload($e, $client, detailed: true);
                $payload['is_owner'] = false;
                $owner = $this->granteePayload($e->client);
                if ($owner !== null) {
                    $payload['owner_nom'] = $owner['nom_complet'];
                    $payload['owner_photo_url'] = $owner['photo_url'];
                }

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
                'grantee' => $this->granteePayload($g->grantee),
                'expires_at' => $g->expires_at?->toIso8601String(),
                'is_active' => true,
                'created_at' => $g->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** Recherche un client par téléphone avant d'accorder l'accès (aperçu bottom sheet). */
    public function lookupGrantee(Request $request): JsonResponse
    {
        $owner = CurrentClient::get();

        $validator = Validator::make($request->all(), [
            'tel' => 'required|regex:/^0\d{9}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $tel = $validator->validated()['tel'];
        $grantee = Client::query()->where('tel', $tel)->first();

        if (! $grantee) {
            return response()->json(['status' => 'error', 'message' => 'Client introuvable avec ce numéro.'], 404);
        }

        if ((int) $grantee->id_client === (int) $owner->id_client) {
            return response()->json(['status' => 'error', 'message' => 'Vous ne pouvez pas vous autoriser vous-même.'], 422);
        }

        return response()->json([
            'status' => 'success',
            'client' => $this->granteePayload($grantee),
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

    private function pageFilePath(int $encodageId, int $pageId): string
    {
        return "/documents/{$encodageId}/pages/{$pageId}/file";
    }

    /** @return array<string, mixed>|null */
    private function granteePayload(?Client $client): ?array
    {
        if (! $client) {
            return null;
        }

        $photoUrl = null;
        if ($client->photo) {
            $v = $client->photoCacheVersion() ?? time();
            $photoUrl = '/clients/'.$client->id_client.'/photo?v='.$v;
        }

        return [
            'id_client' => $client->id_client,
            'nom_complet' => $client->nom_complet,
            'tel' => $client->tel,
            'photo_url' => $photoUrl,
        ];
    }

    /** @return array<string, mixed> */
    private function documentListPayload(Encodage $encodage, Client $viewer, bool $detailed = false): array
    {
        $payload = $this->encodagePayload($encodage, $viewer, detailed: $detailed);
        $payload['associated_clients'] = $this->clientAssociation->clientsListForEncodage(
            $encodage,
            $this->clientPhotos,
            forMobile: true,
        );

        $owner = $this->granteePayload($encodage->client);
        if ($owner !== null) {
            $payload['owner_nom'] = $owner['nom_complet'];
            $payload['owner_photo_url'] = $owner['photo_url'];
        }

        return $payload;
    }
}
