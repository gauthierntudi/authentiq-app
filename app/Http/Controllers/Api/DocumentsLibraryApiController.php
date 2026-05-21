<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentsLibraryService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentsLibraryApiController extends Controller
{
    public function __construct(private DocumentsLibraryService $library) {}

    public function index(Request $request): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $clientId = $request->query('client_id');
        $clientIdFilter = $clientId === null || $clientId === ''
            ? null
            : (int) $clientId;

        $data = $this->library->browse(
            $user,
            $request->query('search'),
            $request->query('status'),
            $clientIdFilter,
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
