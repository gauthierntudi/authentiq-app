<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\ClientKycService;
use App\Support\CurrentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientKycApiController extends Controller
{
    public function __construct(private ClientKycService $kyc) {}

    public function status(): JsonResponse
    {
        $client = CurrentClient::get();

        return response()->json([
            'status' => 'success',
            'kyc' => $this->kyc->kycPayload($client),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $client = CurrentClient::get();

        $validator = Validator::make($request->all(), [
            'recto' => 'required|image|max:8192',
            'verso' => 'nullable|image|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        try {
            $submission = $this->kyc->submit(
                $client,
                $request->file('recto'),
                $request->file('verso'),
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pièce d\'identité envoyée. Vérification en cours.',
                'kyc' => $this->kyc->kycPayload($client->fresh()),
                'submission_id' => $submission->id_submission,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
