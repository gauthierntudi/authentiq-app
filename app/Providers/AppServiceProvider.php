<?php

namespace App\Providers;

use App\Services\UserPhotoStorage;
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
            $photos = app(UserPhotoStorage::class);

            if ($user) {
                $view->with('user', [
                    'photo' => $user->photo,
                    'photo_url' => $photos->photoUrl($user->photo, $user->id_user, $user->photoCacheVersion()),
                    'nom_complet' => $user->nom_complet,
                    'affectation' => $user->affectation,
                    'role' => $user->role,
                ]);

                return;
            }

            $sessionUser = session('user', []);
            if (! empty($sessionUser['id_user']) && empty($sessionUser['photo_url']) && ! empty($sessionUser['photo'])) {
                $sessionUser['photo_url'] = $photos->photoUrl(
                    $sessionUser['photo'],
                    (int) $sessionUser['id_user'],
                    crc32(ltrim(str_replace('../', '', $sessionUser['photo']), '/')),
                );
            }

            $view->with('user', $sessionUser);
        });
    }
}
