<?php

namespace App\Http\Middleware;

use App\Services\ClientAuthService;
use App\Support\CurrentClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClient
{
    public function __construct(private ClientAuthService $clientAuth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $client = $this->clientAuth->resolveFromBearer($request->header('Authorization'));

        if (! $client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token client invalide ou expiré.',
            ], 401);
        }

        if (! $client->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Compte client inactif. Vérifiez votre inscription (OTP).',
            ], 403);
        }

        CurrentClient::set($client);

        try {
            return $next($request);
        } finally {
            CurrentClient::set(null);
        }
    }
}
