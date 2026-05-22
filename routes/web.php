<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EncodagePageController;
use App\Http\Controllers\Web\ClientPageController;
use App\Http\Controllers\Web\CommunePageController;
use App\Http\Controllers\Web\DocPageController;
use App\Http\Controllers\Web\DocumentsLibraryPageController;
use App\Http\Controllers\Web\ProfilePageController;
use App\Http\Controllers\Web\ReportPageController;
use App\Http\Controllers\Web\UserPageController;
use App\Http\Controllers\Web\PublicVerifyController;
use App\Http\Controllers\Web\VillePageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/accueil');

Route::get('/verify/{numero}', [PublicVerifyController::class, 'show'])
    ->where('numero', '[A-Za-z0-9]+')
    ->name('verify.document');
Route::get('/api/public/verify/{numero}', [PublicVerifyController::class, 'api'])
    ->where('numero', '[A-Za-z0-9]+');

Route::middleware('guest.user')->group(function () {
    Route::get('/accueil', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/connexion/otp', [AuthController::class, 'showOtp'])->name('otp');
    Route::redirect('/verify', '/connexion/otp');
});

Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
Route::get('/deconnexion', [AuthController::class, 'logout']);

Route::middleware(['auth.user', 'role.staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/mon-profil', [ProfilePageController::class, 'index'])->name('profile.index');

    Route::get('/gestion-clients', [ClientPageController::class, 'index'])->name('clients.index');
    Route::get('/mes-documents', [DocumentsLibraryPageController::class, 'index'])->name('documents.library');
    Route::get('/encodage-document', [EncodagePageController::class, 'index'])->name('encodage.index');

    Route::get('/rapports/journalier', [ReportPageController::class, 'daily'])->name('reports.daily');
    Route::get('/rapports/mensuel', [ReportPageController::class, 'monthly'])->name('reports.monthly');

    Route::middleware('role.admin')->group(function () {
        Route::get('/gestion-utilisateurs', [UserPageController::class, 'index'])->name('users.index');
        Route::get('/documents', [DocPageController::class, 'index'])->name('docs.index');
        Route::get('/maisons-communales', [CommunePageController::class, 'index'])->name('communes.index');
        Route::get('/regions-villes', [VillePageController::class, 'index'])->name('villes.index');
        Route::redirect('/ajouter-ville', '/regions-villes?tab=ajouter');
        Route::redirect('/afficher-villes', '/regions-villes');
        Route::get('/rapports/global', [ReportPageController::class, 'global'])->name('reports.global');
    });
});
