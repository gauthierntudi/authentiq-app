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

        [$from, $to] = $this->resolveRangeQuery($request);

        return response()->json([
            'status' => 'success',
            'data' => $this->reports->daily($user, $request->query('date'), $from, $to),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $user = CurrentUser::get();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        [$from, $to] = $this->resolveRangeQuery($request);

        return response()->json([
            'status' => 'success',
            'data' => $this->reports->monthly($user, $request->query('month'), $from, $to),
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveRangeQuery(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from || $to) {
            return [$from, $to];
        }

        if ($request->query('date')) {
            $d = (string) $request->query('date');

            return [$d, $d];
        }

        return [null, null];
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
