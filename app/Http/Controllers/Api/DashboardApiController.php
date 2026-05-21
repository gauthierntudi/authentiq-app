<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function stats(): JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        $agentsTotal = User::query()->count();
        $agentsField = User::query()->where('role', 'user')->count();
        $agentsAdmin = User::query()->where('role', 'admin')->count();

        $clientsTotal = Client::query()->count();
        $clientsActive = Client::query()->where('is_active', true)->count();

        return response()->json([
            'status' => 'success',
            'stats' => [
                'agents' => $agentsTotal,
                'agents_field' => $agentsField,
                'agents_admin' => $agentsAdmin,
                'clients' => $clientsTotal,
                'clients_active' => $clientsActive,
            ],
        ]);
    }
}
