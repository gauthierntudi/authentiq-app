<?php

namespace App\Http\Middleware;

use App\Services\UserAccessService;
use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function __construct(private UserAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = CurrentUser::get();

        if (! $this->access->isStaff($user)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Accès non autorisé.',
                ], 403);
            }

            abort(403, 'Accès non autorisé.');
        }

        return $next($request);
    }
}
