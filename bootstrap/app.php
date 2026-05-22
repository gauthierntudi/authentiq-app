<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.user' => \App\Http\Middleware\AuthenticateUser::class,
            'guest.user' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'auth.client' => \App\Http\Middleware\AuthenticateClient::class,
            'role.admin' => \App\Http\Middleware\EnsureAdmin::class,
            'role.staff' => \App\Http\Middleware\EnsureStaff::class,
        ]);

        // Filet de sécurité si une route mobile repasse sous « web »
        $middleware->validateCsrfTokens(except: [
            'api/mobile/client',
            'api/mobile/client/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
