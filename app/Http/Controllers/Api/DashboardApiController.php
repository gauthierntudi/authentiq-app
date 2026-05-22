<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Encodage;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function __construct(private UserAccessService $access) {}

    public function stats(): JsonResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Non connecté'], 401);
        }

        if ($this->access->isAdmin($user)) {
            $agentsTotal = User::query()->count();
            $agentsField = User::query()->where('role', 'user')->count();
            $agentsAdmin = User::query()->where('role', 'admin')->count();
            $clientsTotal = Client::query()->count();
            $clientsActive = Client::query()->where('is_active', true)->count();
        } else {
            $agentsTotal = 1;
            $agentsField = 1;
            $agentsAdmin = 0;
            $clientsQuery = $this->access->scopeClients(Client::query(), $user);
            $clientsTotal = (clone $clientsQuery)->count();
            $clientsActive = (clone $clientsQuery)->where('is_active', true)->count();
        }

        $encodagesQuery = $this->access->scopeEncodages(Encodage::query(), $user);
        $encodagesTotal = (clone $encodagesQuery)->count();
        $encodagesComplete = (clone $encodagesQuery)->where('status', 'complete')->count();

        return response()->json([
            'status' => 'success',
            'stats' => [
                'agents' => $agentsTotal,
                'agents_field' => $agentsField,
                'agents_admin' => $agentsAdmin,
                'clients' => $clientsTotal,
                'clients_active' => $clientsActive,
                'encodages' => $encodagesTotal,
                'encodages_complete' => $encodagesComplete,
                'scoped_to_user' => ! $this->access->isAdmin($user),
            ],
        ]);
    }
}
