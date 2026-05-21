<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CurrentUser::check()) {
            return redirect()->to('/accueil');
        }

        return $next($request);
    }
}
