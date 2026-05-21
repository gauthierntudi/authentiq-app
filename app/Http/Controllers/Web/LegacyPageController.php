<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Pages pas encore migrées en Blade — placeholders pour conserver les URLs.
 */
class LegacyPageController extends Controller
{
    public function users(): View
    {
        return view('pages.placeholder', ['title' => 'Gestion utilisateurs']);
    }

    public function clients(): View
    {
        return view('pages.placeholder', ['title' => 'Gestion clients']);
    }

    public function docs(): View
    {
        return view('pages.placeholder', ['title' => 'Documents']);
    }

    public function encodage(): View
    {
        return view('pages.placeholder', ['title' => 'Encodage document']);
    }

    public function communes(): View
    {
        return view('pages.placeholder', ['title' => 'Maisons communales']);
    }

    public function addVille(): View
    {
        return view('pages.placeholder', ['title' => 'Ajouter ville']);
    }

    public function showVilles(): View
    {
        return view('pages.placeholder', ['title' => 'Afficher villes']);
    }
}
