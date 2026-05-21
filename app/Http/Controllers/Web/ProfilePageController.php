<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Controller;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function __construct(private ProfileApiController $profileApi) {}

    public function index(): View|RedirectResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return redirect()->to('/accueil');
        }

        return view('pages.profile', [
            'profile' => $this->profileApi->formatProfile($user),
        ]);
    }
}
