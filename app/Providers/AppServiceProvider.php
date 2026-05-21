<?php

namespace App\Providers;

use App\Support\CurrentUser;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials.header-raw', function ($view) {
            $user = CurrentUser::get();
            $view->with('user', $user ? [
                'photo' => $user->photo,
                'nom_complet' => $user->nom_complet,
                'affectation' => $user->affectation,
                'role' => $user->role,
            ] : session('user', []));
        });
    }
}
