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
use App\Services\ClientPhotoStorage;
use App\Services\DocumentStorage;
use App\Services\EncodageQrService;
use App\Services\OtpService;
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
        private DocumentStorage $storage,
        private EncodageQrService $qrService,
        private TextractService $textract,
        private RekognitionService $rekognition,
        private ClientPhotoStorage $clientPhotos,
        private ClientDuplicateGuard $duplicateGuard,
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
            ->get(['id_doc', 'nom_doc', 'type_doc', 'montant', 'duree']);

        return response()->json($docs);
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

                    foreach ($encodage->pages as $page) {
                        $this->storage->delete($page->file_path);
                    }
                    $encodage->pages()->delete();

                    $encodage->update([
                        'page_count' => $pageCount,
                        'ocrTextFiles' => $ocrText,
                    ]);
                } else {
                    $encodage = Encodage::query()->create([
                        'id_user' => $user->id_user,
                        'status' => 'incomplete',
                        'id_commune' => $user->id_commune,
                        'id_province' => $user->id_province,
                        'id_ville' => $user->id_ville,
                        'affectation' => $user->affectation,
                        'page_count' => $pageCount,
                        'ocrTextFiles' => $ocrText,
                    ]);
                    $encodageId = $encodage->id_encodage;
                }

                $savedPages = [];
                $filePaths = [];

                for ($i = 0; $i < $pageCount; $i++) {
                    $file = $request->file("file_{$i}");
                    if (! $file || ! $file->isValid()) {
                        continue;
                    }

                    $stored = $this->storage->storeUploadedPage($file, $encodageId, $i + 1);
                    $filePath = $stored['path'];
                    $pageOcr = (string) $request->input("ocr_{$i}", '');

                    $page = EncodagePage::query()->create([
                        'id_encodage' => $encodageId,
                        'page_number' => $i + 1,
                        'file_path' => $filePath,
                        'file_size' => $stored['size'],
                        'ocr_text' => $pageOcr,
                    ]);

                    $savedPages[] = [
                        'id_page' => $page->id_page,
                        'page_number' => $page->page_number,
                        'file_path' => $this->storage->urlForKnownPath($filePath),
                        'ocr_text' => $pageOcr,
                    ];
                    $filePaths[] = $filePath;
                }

                if ($filePaths === []) {
                    throw new \RuntimeException('Aucun fichier uploadé avec succès.');
                }

                if (count($savedPages) < $pageCount) {
                    throw new \RuntimeException(
                        count($savedPages).' page(s) reçue(s) sur '.$pageCount.' attendue(s). '
                        .'Reprenez l\'étape scan/OCR (Suivant) pour enregistrer toutes les pages.'
                    );
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

        $clients = Client::query()
            ->where(function ($q) use ($query) {
                $q->where('nom_complet', 'like', "%{$query}%")
                    ->orWhere('tel', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
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
            $clientId = (int) $request->input('clientId', 0);
            $requiresOtp = false;
            $message = 'Client sauvegardé.';

            if ($clientId > 0) {
                $client = Client::query()->find($clientId);
                if (! $client) {
                    return response()->json(['status' => 'error', 'message' => 'Client introuvable.'], 404);
                }

                $encodage->update(['id_client' => $clientId]);

                if (! $client->is_active) {
                    $requiresOtp = true;
                    $message = $this->otpMessageAfterIssue($this->otpService->issueForClient($client));
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
                    'type_piece_identite' => $typePiece ?: null,
                    'numero_national' => $typePiece === 'CNI' ? $numeroPiece : null,
                    'numero_passeport' => $typePiece === 'Passeport' ? $numeroPiece : null,
                    'is_active' => false,
                ]);

                $client->update([
                    'photo' => $this->clientPhotos->store($client, $request->file('clientPhoto')),
                ]);

                $clientId = $client->id_client;
                $encodage->update(['id_client' => $clientId]);
                $requiresOtp = true;
                $message = $this->otpMessageAfterIssue($this->otpService->issueForClient($client));
            }

            return response()->json([
                'status' => 'success',
                'clientId' => $clientId,
                'requires_otp' => $requiresOtp,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /** @param  array{whatsapp_sent: bool, mail_sent: bool}  $delivery */
    private function otpMessageAfterIssue(array $delivery): string
    {
        $message = 'Client enregistré. Validez le code OTP pour activer le compte.';
        if ($delivery['whatsapp_sent'] || $delivery['mail_sent']) {
            $channels = [];
            if ($delivery['whatsapp_sent']) {
                $channels[] = 'WhatsApp';
            }
            if ($delivery['mail_sent']) {
                $channels[] = 'email';
            }
            $message .= ' Code envoyé par '.implode(' et ', $channels).'.';
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
            'document' => [
                'nom_doc' => $encodage->doc?->nom_doc,
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

        if (! $encodage->id_client) {
            return response()->json(['status' => 'error', 'message' => 'Client requis avant finalisation.']);
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
            'pages' => $pages,
        ]);
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
            Encodage::query()->where('id_encodage', $encodageId)->update(['page_count' => $count]);

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
