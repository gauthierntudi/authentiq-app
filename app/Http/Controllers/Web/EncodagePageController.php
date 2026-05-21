<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EncodagePageController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = CurrentUser::get();

        if (! $user) {
            return redirect()->to('/accueil');
        }

        if (! $user->id_commune) {
            return redirect()->to('/dashboard')
                ->with('error', 'Vous devez être affecté à une commune pour encoder des documents.');
        }

        return view('pages.encodage-document', [
            'user' => $user,
        ]);
    }
}
