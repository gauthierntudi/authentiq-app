<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Doc;
use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Models\User;
use App\Jobs\ProcessEncodagePageTextract;
use App\Services\ClientDuplicateGuard;
use App\Services\ClientOnboardingService;
use App\Services\ClientPhotoStorage;
use App\Services\DocumentStorage;
use App\Services\EncodageClientAssociationService;
use App\Services\EncodageQrService;
use App\Services\OtpService;
use App\Services\Pdf\PdfRasterizerClient;
use App\Services\RekognitionService;
use App\Services\TextractService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EncodageWorkflowApiController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private ClientOnboardingService $clientOnboarding,
        private DocumentStorage $storage,
        private EncodageQrService $qrService,
        private TextractService $textract,
        private RekognitionService $rekognition,
        private ClientPhotoStorage $clientPhotos,
        private ClientDuplicateGuard $duplicateGuard,
        private EncodageClientAssociationService $clientAssociation,
        private PdfRasterizerClient $pdfRasterizer,
    ) {}

    private function authUser(): User|JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        if (! $user->id_commune) {
            return response()->json([
                'status' => 'error',
                'message' => 'Utilisateur non affecté à une commune.',
            ], 403);
        }

        return $user;
    }

    private function encodageForUser(int $encodageId, User $user): ?Encodage
    {
        $query = Encodage::query()->where('id_encodage', $encodageId);

        if ($user->role !== 'admin') {
            $query->where('id_user', $user->id_user);
        }

        return $query->first();
    }

    private function assertEncodageEditable(Encodage $encodage, User $user): ?JsonResponse
    {
        if ($user->role === 'admin') {
            return null;
        }

        if ($encodage->status !== 'incomplete') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cet encodage est finalisé ou expiré : modification impossible.',
            ], 423);
        }

        return null;
    }

    /** Legacy: getDocTypes — retourne un tableau JSON direct. */
    public function docTypes(): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $docs = Doc::query()
            ->orderBy('nom_doc')
            ->get(['id_doc', 'nom_doc', 'type_doc', 'montant', 'duree', 'ownership']);

        return response()->json($docs);
    }

    /** Rasterisation PDF via microservice Rust (fichiers lourds). */
    public function rasterizePdf(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->pdfRasterizer->isEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service PDF serveur désactivé.',
            ], 503);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $uploaded */
        $uploaded = $request->file('file');

        try {
            $result = $this->pdfRasterizer->rasterize($uploaded);

            $pages = array_map(static function (array $page): array {
                return [
                    'page_number' => (int) ($page['page_number'] ?? 0),
                    'width' => (int) ($page['width'] ?? 0),
                    'height' => (int) ($page['height'] ?? 0),
                    'mime' => $page['mime'] ?? 'image/jpeg',
                    'image' => 'data:image/jpeg;base64,'.($page['data_base64'] ?? ''),
                ];
            }, $result['pages']);

            return response()->json([
                'status' => 'success',
                'page_count' => $result['page_count'],
                'pages' => $pages,
                'meta' => $result['meta'],
                'source' => 'pdf-service',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /** Legacy: saveImageOCR */
    public function saveImageOcr(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $pageCount = (int) $request->input('pageCount', 0);
        if ($pageCount === 0) {
            return response()->json(['status' => 'error', 'message' => 'Aucune page à sauvegarder.']);
        }

        try {
            return DB::transaction(function () use ($request, $user, $pageCount) {
                $encodageId = (int) $request->input('encodageId', 0);
                $ocrText = (string) $request->input('ocrText', '');

                if ($encodageId > 0) {
                    $encodage = $this->encodageForUser($encodageId, $user);
                    if (! $encodage) {
                        return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
                    }

                    if ($blocked = $this->assertEncodageEditable($encodage, $user)) {
                        return $blocked;
                    }

                    $encodage->update(['ocrTextFiles' => $ocrText]);
                } else {
                    $encodage = Encodage::query()->create([
                        'id_user' => $user->id_user,
                        'status' => 'incomplete',
                        'id_commune' => $user->id_commune,
                        'id_province' => $user->id_province,
                        'id_ville' => $user->id_ville,
                        'affectation' => $user->affectation,
                        'page_count' => 0,
                        'ocrTextFiles' => $ocrText,
                    ]);
                    $encodageId = $encodage->id_encodage;
                }

                [$savedPages, $filePaths] = $this->persistPagesFromRequest($request, $encodageId, $pageCount);

                if ($savedPages === []) {
                    throw new \RuntimeException('Aucune page enregistrée.');
                }

                Encodage::query()->where('id_encodage', $encodageId)->update([
                    'files' => implode(',', $filePaths),
                    'page_count' => count($savedPages),
                ]);

                $textractQueued = $this->queueTextractJobs($savedPages);

                return response()->json([
                    'status' => 'success',
                    'encodageId' => $encodageId,
                    'pageCount' => count($savedPages),
                    'pages' => $savedPages,
                    'textract_queued' => $textractQueued,
                    'message' => count($savedPages).' page(s) sauvegardée(s).',
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function ocrStatus(int $id): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->encodageForUser($id, $user)) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        $pages = EncodagePage::query()
            ->where('id_encodage', $id)
            ->orderBy('page_number')
            ->get()
            ->map(fn (EncodagePage $p) => [
                'id_page' => $p->id_page,
                'page_number' => $p->page_number,
                'textract_status' => $p->textract_status,
                'ocr_text' => $p->ocr_text,
            ]);

        $pending = $pages->contains(fn ($p) => in_array($p['textract_status'], ['queued', 'processing'], true));

        return response()->json([
            'status' => 'success',
            'pages' => $pages,
            'all_done' => ! $pending,
            'combined_ocr' => Encodage::query()->where('id_encodage', $id)->value('ocrTextFiles'),
        ]);
    }

    /** Legacy: searchClients — tableau JSON direct. */
    public function searchClients(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $clientsQuery = Client::query()
            ->where(function ($q) use ($query) {
                $q->where('nom_complet', 'like', "%{$query}%")
                    ->orWhere('tel', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            });

        if ($user->role !== 'admin') {
            $clientsQuery
                ->where('id_province', $user->id_province)
                ->where('id_ville', $user->id_ville);
        }

        $clients = $clientsQuery
            ->orderBy('nom_complet')
            ->limit(20)
            ->get(['id_client', 'nom_complet', 'tel', 'email', 'is_active']);

        return response()->json($clients);
    }

    public function saveClient(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodageId = (int) $request->input('encodageId', 0);
        if ($encodageId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'ID encodage manquant.']);
        }

        $encodage = $this->encodageForUser($encodageId, $user);
        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        if ($blocked = $this->assertEncodageEditable($encodage, $user)) {
            return $blocked;
        }

        try {
            $clientIds = $this->parseClientIdsFromRequest($request);
            $singleClientId = (int) $request->input('clientId', 0);
            $requiresOtp = false;
            $message = 'Client(s) sauvegardé(s).';
            $lastClientId = null;

            if ($clientIds === [] && $singleClientId > 0) {
                $clientIds = [$singleClientId];
            }

            if ($clientIds !== []) {
                foreach ($clientIds as $cid) {
                    $client = Client::query()->find($cid);
                    if (! $client) {
                        return response()->json(['status' => 'error', 'message' => 'Client introuvable.'], 404);
                    }

                    if ($user->role !== 'admin'
                        && ((int) $client->id_province !== (int) $user->id_province
                            || (int) $client->id_ville !== (int) $user->id_ville)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Ce client n\'appartient pas à votre province/ville.',
                        ], 403);
                    }

                    if (! $client->is_active) {
                        $requiresOtp = true;
                        $message = $this->otpMessageAfterIssue($this->otpService->issueForClient($client));
                    }
                }

                $this->clientAssociation->syncClients($encodage, $clientIds, $user);
                $lastClientId = $clientIds[0];

                if ($error = $this->clientAssociation->validateForDoc($encodage->fresh())) {
                    return response()->json(['status' => 'error', 'message' => $error], 422);
                }
            } else {
                $nom = trim((string) $request->input('clientNom', ''));
                $tel = trim((string) $request->input('clientTel', ''));
                $email = trim((string) $request->input('clientEmail', ''));

                if ($nom === '' || $tel === '') {
                    return response()->json(['status' => 'error', 'message' => 'Nom et téléphone requis.']);
                }

                if (! $request->hasFile('clientPhoto')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La photo du client est obligatoire (capture via la caméra).',
                    ]);
                }

                $typePiece = (string) $request->input('clientTypePiece', '');
                $numeroPiece = (string) $request->input('clientNumeroPiece', '');

                $photoBytes = null;
                if ($request->hasFile('clientPhoto')) {
                    $photoBytes = file_get_contents($request->file('clientPhoto')->getRealPath()) ?: null;
                }

                $duplicateCheck = $this->duplicateGuard->check(
                    $tel,
                    $email,
                    $typePiece,
                    $numeroPiece,
                    null,
                    null,
                    $photoBytes,
                );

                if (! $duplicateCheck['allowed']) {
                    return response()->json([
                        'status' => 'error',
                        'duplicate' => true,
                        'message' => $duplicateCheck['conflict']['message'] ?? 'Client déjà enregistré.',
                        'conflict' => $duplicateCheck['conflict'],
                        'client' => $duplicateCheck['client'],
                    ], 409);
                }

                $client = Client::query()->create([
                    'nom_complet' => $nom,
                    'tel' => $tel,
                    'email' => $email,
                    'id_province' => $user->id_province,
                    'id_ville' => $user->id_ville,
                    'type_piece_identite' => $typePiece ?: null,
                    'numero_national' => $typePiece === 'CNI' ? $numeroPiece : null,
                    'numero_passeport' => $typePiece === 'Passeport' ? $numeroPiece : null,
                    'is_active' => false,
                ]);

                $client->update([
                    'photo' => $this->clientPhotos->store($client, $request->file('clientPhoto')),
                ]);

                $newClientId = (int) $client->id_client;
                $mergedIds = array_values(array_unique(array_merge($clientIds, [$newClientId])));
                $this->clientAssociation->syncClients($encodage, $mergedIds, $user);
                $lastClientId = $newClientId;
                $requiresOtp = true;
                $message = $this->otpMessageAfterIssue($this->clientOnboarding->onboardStaffCreatedClient($client->fresh()));

                if ($error = $this->clientAssociation->validateForDoc($encodage->fresh())) {
                    return response()->json(['status' => 'error', 'message' => $error], 422);
                }
            }

            if ($lastClientId === null) {
                return response()->json(['status' => 'error', 'message' => 'Associez au moins un client.']);
            }

            $encodage->refresh();

            if ($request->boolean('completeStep')) {
                if ($error = $this->clientAssociation->validateForFinalize($encodage)) {
                    return response()->json(['status' => 'error', 'message' => $error], 422);
                }
            }

            $associatedClients = $this->formatAssociatedClientsPayload($encodage);

            return response()->json([
                'status' => 'success',
                'clientId' => $lastClientId,
                'clientIds' => $this->clientAssociation->associatedClientIds($encodage),
                'associated_clients' => $associatedClients,
                'requires_otp' => $requiresOtp,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /** @param  array{whatsapp_sent?: bool, mail_sent?: bool, credentials_mail_sent?: bool}  $delivery */
    private function otpMessageAfterIssue(array $delivery): string
    {
        $message = 'Client enregistré. Validez le code OTP pour activer le compte.';
        if ($delivery['whatsapp_sent'] ?? false) {
            $message .= ' OTP envoyé par WhatsApp.';
        }
        if ($delivery['credentials_mail_sent'] ?? false) {
            $message .= ' Identifiants de connexion et OTP envoyés par email.';
        } elseif ($delivery['mail_sent'] ?? false) {
            $message .= ' Code OTP envoyé par email.';
        }

        return $message;
    }

    public function saveDocument(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodageId = (int) $request->input('encodageId', 0);
        $docTypeId = (int) $request->input('docType', 0);

        if ($encodageId <= 0 || $docTypeId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Données manquantes.']);
        }

        $encodage = $this->encodageForUser($encodageId, $user);
        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        if ($blocked = $this->assertEncodageEditable($encodage, $user)) {
            return $blocked;
        }

        $doc = Doc::query()->find($docTypeId);
        if (! $doc) {
            return response()->json(['status' => 'error', 'message' => 'Type de document introuvable.']);
        }

        $encodage->update([
            'id_doc' => $docTypeId,
            'type_doc' => $doc->type_doc,
            'montant' => $request->input('docMontant'),
            'date_emission' => $request->input('docDateEmission') ?: null,
            'date_expiration' => $request->input('docDateExpiration') ?: null,
        ]);

        $encodage->refresh();

        if ($error = $this->clientAssociation->validateForDoc($encodage)) {
            return response()->json(['status' => 'error', 'message' => $error], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Document sauvegardé.']);
    }

    public function recap(int $id): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodage = Encodage::query()
            ->with(['client', 'doc', 'pages'])
            ->where('id_encodage', $id);

        if ($user->role !== 'admin') {
            $encodage->where('id_user', $user->id_user);
        }

        $encodage = $encodage->first();

        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage non trouvé.'], 404);
        }

        $pages = $encodage->pages->map(fn (EncodagePage $p) => [
            'id_page' => $p->id_page,
            'page_number' => $p->page_number,
            'file_path' => $this->storage->urlForKnownPath($p->file_path),
            'file_size' => $p->file_size,
            'ocr_text' => $p->ocr_text,
        ])->values();

        $qr = null;
        if ($encodage->numero) {
            $qr = [
                'numero' => $encodage->numero,
                'verify_url' => url('/verify/'.$encodage->numero),
                'qr_url' => $this->storage->urlForKnownPath($encodage->qr_path),
            ];
        }

        return response()->json([
            'status' => 'success',
            'encodage' => $encodage,
            'pageCount' => $encodage->page_count,
            'pages' => $pages,
            'client' => [
                'nom_complet' => $encodage->client?->nom_complet,
                'tel' => $encodage->client?->tel,
                'email' => $encodage->client?->email,
                'photo_url' => $this->clientPhotos->photoUrl(
                    $encodage->client?->photo,
                    $encodage->client?->id_client,
                    $encodage->client?->photoCacheVersion(),
                ),
            ],
            'associated_clients' => $this->formatAssociatedClientsPayload($encodage),
            'document' => [
                'nom_doc' => $encodage->doc?->nom_doc,
                'ownership' => $encodage->doc?->ownership,
            ],
            'qr' => $qr,
        ]);
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodage = $this->encodageForUser($id, $user);
        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        if ($encodage->status === 'complete') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cet encodage est déjà finalisé.',
            ], 423);
        }

        if ($blocked = $this->assertEncodageEditable($encodage, $user)) {
            return $blocked;
        }

        if ($error = $this->clientAssociation->validateForFinalize($encodage)) {
            return response()->json(['status' => 'error', 'message' => $error], 422);
        }

        if (! $encodage->id_doc) {
            return response()->json(['status' => 'error', 'message' => 'Type de document requis avant finalisation.']);
        }

        $encodage->update(['status' => 'complete']);
        $encodage->refresh();

        $qr = $this->qrService->issueForEncodage($encodage);

        return response()->json([
            'status' => 'success',
            'message' => 'Encodage finalisé.',
            'numero' => $qr['numero'],
            'verify_url' => $qr['verify_url'],
            'qr_url' => $qr['qr_url'],
        ]);
    }

    public function incomplete(): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodage = Encodage::query()
            ->where('id_user', $user->id_user)
            ->where('status', 'incomplete')
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'encodage' => $encodage,
        ]);
    }

    public function pageFile(int $id, int $pageId): Response|JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->encodageForUser($id, $user)) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        $page = EncodagePage::query()
            ->where('id_encodage', $id)
            ->where('id_page', $pageId)
            ->first();

        if (! $page) {
            return response()->json(['status' => 'error', 'message' => 'Page non trouvée.'], 404);
        }

        $bytes = $this->storage->diskGet($page->file_path);
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

    public function pages(int $id): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->encodageForUser($id, $user)) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        $pages = EncodagePage::query()
            ->where('id_encodage', $id)
            ->orderBy('page_number')
            ->get()
            ->map(fn (EncodagePage $p) => [
                'id_page' => $p->id_page,
                'page_number' => $p->page_number,
                'file_path' => $this->storage->urlForKnownPath($p->file_path),
                'file_size' => $p->file_size,
                'ocr_text' => $p->ocr_text,
                'quality_score' => $p->quality_score,
                'created_at' => $p->created_at,
            ]);

        return response()->json([
            'status' => 'success',
            'pages' => $pages,
            'total' => $pages->count(),
        ]);
    }

    /** Reprise depuis le dashboard (continueEncodageId). */
    public function resume(int $id): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $encodageQuery = Encodage::query()
            ->with([
                'pages' => fn ($q) => $q->select('id_page', 'id_encodage', 'page_number', 'file_path', 'ocr_text'),
                'client:id_client,nom_complet',
                'doc:id_doc,nom_doc,type_doc',
            ])
            ->where('id_encodage', $id);

        if ($user->role !== 'admin') {
            $encodageQuery->where('id_user', $user->id_user)->where('status', 'incomplete');
        }

        $encodage = $encodageQuery->first();

        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        $pages = $encodage->pages->map(fn (EncodagePage $p) => [
            'id_page' => $p->id_page,
            'page_number' => $p->page_number,
            'file_path' => $this->storage->urlForKnownPath($p->file_path),
            'ocr_text' => $p->ocr_text,
        ])->values();

        return response()->json([
            'status' => 'success',
            'encodage' => [
                'id_encodage' => $encodage->id_encodage,
                'id_client' => $encodage->id_client,
                'id_doc' => $encodage->id_doc,
                'status' => $encodage->status,
                'montant' => $encodage->montant,
                'date_emission' => $encodage->date_emission,
                'date_expiration' => $encodage->date_expiration,
                'page_count' => $encodage->page_count,
            ],
            'associated_clients' => $this->formatAssociatedClientsPayload($encodage),
            'pages' => $pages,
        ]);
    }

    /** @return list<int> */
    private function parseClientIdsFromRequest(Request $request): array
    {
        $raw = $request->input('clientIds');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? array_values(array_unique(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0)))
                : [];
        }

        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw), fn ($id) => $id > 0)));
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private function formatAssociatedClientsPayload(Encodage $encodage): array
    {
        $ids = $this->clientAssociation->associatedClientIds($encodage);
        if ($ids === []) {
            return [];
        }

        $clients = Client::query()
            ->whereIn('id_client', $ids)
            ->get(['id_client', 'nom_complet', 'tel', 'email', 'is_active'])
            ->keyBy('id_client');

        $payload = [];
        foreach ($ids as $id) {
            $client = $clients->get($id);
            if (! $client) {
                continue;
            }
            $payload[] = [
                'id_client' => $client->id_client,
                'nom_complet' => $client->nom_complet,
                'tel' => $client->tel,
                'email' => $client->email,
                'is_active' => (bool) $client->is_active,
                'is_primary' => (int) $encodage->id_client === (int) $client->id_client,
            ];
        }

        return $payload;
    }

    public function deletePage(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $pageId = (int) $request->input('pageId', 0);
        $encodageId = (int) $request->input('encodageId', 0);

        if ($pageId <= 0 || $encodageId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Paramètres manquants.']);
        }

        $encodage = $this->encodageForUser($encodageId, $user);
        if (! $encodage) {
            return response()->json(['status' => 'error', 'message' => 'Encodage introuvable.'], 404);
        }

        if ($blocked = $this->assertEncodageEditable($encodage, $user)) {
            return $blocked;
        }

        $page = EncodagePage::query()
            ->where('id_page', $pageId)
            ->where('id_encodage', $encodageId)
            ->first();

        if (! $page) {
            return response()->json(['status' => 'error', 'message' => 'Page non trouvée.']);
        }

        try {
            $this->storage->delete($page->file_path);
            $page->delete();

            $remaining = EncodagePage::query()
                ->where('id_encodage', $encodageId)
                ->orderBy('page_number')
                ->get();

            $count = $remaining->count();
            $filesList = $remaining->pluck('file_path')->filter()->implode(',');
            Encodage::query()->where('id_encodage', $encodageId)->update([
                'page_count' => $count,
                'files' => $filesList,
            ]);

            $pagesPayload = $remaining->map(fn (EncodagePage $p) => [
                'id_page' => $p->id_page,
                'page_number' => $p->page_number,
                'file_path' => $this->storage->urlForKnownPath($p->file_path),
                'ocr_text' => $p->ocr_text,
            ])->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Page supprimée.',
                'page_count' => $count,
                'pages' => $pagesPayload,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Enregistre les pages sans tout supprimer : conserve les pages existantes (pageId_N) sans nouveau fichier.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function persistPagesFromRequest(Request $request, int $encodageId, int $pageCount): array
    {
        $savedPages = [];
        $filePaths = [];
        $keptPageIds = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $pageOcr = (string) $request->input("ocr_{$i}", '');
            $existingPageId = (int) $request->input("pageId_{$i}", 0);
            $file = $request->file("file_{$i}");
            $hasFile = $file && $file->isValid();

            if ($existingPageId > 0 && ! $hasFile) {
                $page = EncodagePage::query()
                    ->where('id_page', $existingPageId)
                    ->where('id_encodage', $encodageId)
                    ->first();

                if (! $page) {
                    throw new \RuntimeException('Page '.$existingPageId.' introuvable pour cet encodage.');
                }

                $page->update([
                    'page_number' => $i + 1,
                    'ocr_text' => $pageOcr,
                ]);

                $keptPageIds[] = $page->id_page;
                $filePaths[] = $page->file_path;
                $savedPages[] = $this->pagePayload($page, $pageOcr);

                continue;
            }

            if (! $hasFile) {
                throw new \RuntimeException(
                    'Page '.($i + 1).' : envoyez le fichier image ou l\'identifiant pageId_'.$i.' d\'une page déjà enregistrée.'
                );
            }

            if ($existingPageId > 0) {
                $old = EncodagePage::query()
                    ->where('id_page', $existingPageId)
                    ->where('id_encodage', $encodageId)
                    ->first();

                if ($old) {
                    $this->storage->delete($old->file_path);
                    $old->delete();
                }
            }

            $stored = $this->storage->storeUploadedPage($file, $encodageId, $i + 1);
            $filePath = $stored['path'];

            $page = EncodagePage::query()->create([
                'id_encodage' => $encodageId,
                'page_number' => $i + 1,
                'file_path' => $filePath,
                'file_size' => $stored['size'],
                'ocr_text' => $pageOcr,
            ]);

            $keptPageIds[] = $page->id_page;
            $filePaths[] = $filePath;
            $savedPages[] = $this->pagePayload($page, $pageOcr);
        }

        $orphans = EncodagePage::query()
            ->where('id_encodage', $encodageId)
            ->when($keptPageIds !== [], fn ($q) => $q->whereNotIn('id_page', $keptPageIds))
            ->get();

        foreach ($orphans as $orphan) {
            $this->storage->delete($orphan->file_path);
            $orphan->delete();
        }

        return [$savedPages, $filePaths];
    }

    /** @return array<string, mixed> */
    private function pagePayload(EncodagePage $page, string $ocrText): array
    {
        return [
            'id_page' => $page->id_page,
            'page_number' => $page->page_number,
            'file_path' => $this->storage->urlForKnownPath($page->file_path),
            'ocr_text' => $ocrText !== '' ? $ocrText : $page->ocr_text,
        ];
    }

    /**
     * @param  list<array{id_page: int}>  $savedPages
     */
    private function queueTextractJobs(array $savedPages): bool
    {
        if (! $this->textract->enabled()) {
            return false;
        }

        $queue = (string) config('authentiq.queue', 'database');

        foreach ($savedPages as $page) {
            $pageId = (int) ($page['id_page'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            EncodagePage::query()->where('id_page', $pageId)->update(['textract_status' => 'queued']);
            ProcessEncodagePageTextract::dispatch($pageId)->onQueue($queue);
        }

        return true;
    }
}
