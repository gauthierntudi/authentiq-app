<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CurrentUser::check()) {
            return redirect()->to('/dashboard');
        }

        return $next($request);
    }
}
