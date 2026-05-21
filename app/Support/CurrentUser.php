<?php

namespace App\Support;

use App\Models\User;

class CurrentUser
{
    public static function get(): ?User
    {
        $id = session('user.id_user');

        if (! $id) {
            return null;
        }

        return User::query()
            ->with(['province', 'ville', 'commune'])
            ->find($id);
    }

    public static function check(): bool
    {
        return session()->has('user.id_user');
    }

    public static function loginFromModel(User $user): void
    {
        session([
            'user' => [
                'id_user' => $user->id_user,
                'nom_complet' => $user->nom_complet,
                'tel' => $user->tel,
                'email' => $user->email,
                'role' => $user->role,
                'id_province' => $user->id_province,
                'id_ville' => $user->id_ville,
                'id_commune' => $user->id_commune,
                'affectation' => $user->affectation,
                'photo' => $user->photo,
            ],
        ]);
    }

    public static function logout(): void
    {
        session()->forget('user');
        session()->invalidate();
        session()->regenerateToken();
    }
}
