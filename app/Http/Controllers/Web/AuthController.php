<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login-raw');
    }

    public function showOtp(): View
    {
        return view('auth.otp-raw');
    }

    public function logout(): RedirectResponse
    {
        CurrentUser::logout();

        return redirect()->to('/accueil');
    }
}
