<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function daily(Request $request): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->reports->daily($user, $request->query('date')),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->reports->monthly($user, $request->query('month')),
        ]);
    }

    public function global(): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->reports->global($user),
        ]);
    }
}
