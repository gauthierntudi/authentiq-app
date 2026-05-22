<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\CommuneApiController;
use App\Http\Controllers\Api\DocApiController;
use App\Http\Controllers\Api\DocumentsLibraryApiController;
use App\Http\Controllers\Api\EncodageApiController;
use App\Http\Controllers\Api\EncodageWorkflowApiController;
use App\Http\Controllers\Api\GeoApiController;
use App\Http\Controllers\Api\Mobile\ClientAuthApiController as MobileClientAuthApiController;
use App\Http\Controllers\Api\Mobile\ClientDocumentApiController as MobileClientDocumentApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\VilleApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/auth/login', [AuthApiController::class, 'login']);
    Route::post('/auth/send-otp', [AuthApiController::class, 'sendLoginOtp']);
    Route::post('/auth/verify-otp', [AuthApiController::class, 'verifyOtp']);
    Route::post('/auth/resend-otp', [AuthApiController::class, 'resendOtp']);

    /*
    | API mobile Flutter — clients finaux (Bearer token, pas de session staff).
    */
    Route::prefix('mobile/client')->group(function () {
        Route::get('/geo/provinces', [GeoApiController::class, 'provinces']);
        Route::get('/geo/villes-by-province', [GeoApiController::class, 'villesByProvince']);

        Route::post('/register', [MobileClientAuthApiController::class, 'register']);
        Route::post('/login', [MobileClientAuthApiController::class, 'login']);
        Route::post('/send-otp', [MobileClientAuthApiController::class, 'sendOtp']);
        Route::post('/verify-otp', [MobileClientAuthApiController::class, 'verifyOtp']);

        Route::middleware('auth.client')->group(function () {
            Route::get('/me', [MobileClientAuthApiController::class, 'me']);
            Route::post('/logout', [MobileClientAuthApiController::class, 'logout']);

            Route::get('/documents', [MobileClientDocumentApiController::class, 'index']);
            Route::post('/documents/verify', [MobileClientDocumentApiController::class, 'verify']);
            Route::get('/documents/verify-grants', [MobileClientDocumentApiController::class, 'listGrants']);
            Route::post('/documents/verify-grants', [MobileClientDocumentApiController::class, 'grant']);
            Route::delete('/documents/verify-grants/{grantId}', [MobileClientDocumentApiController::class, 'revokeGrant'])
                ->whereNumber('grantId');
        });
    });

    Route::middleware(['auth.user', 'role.staff'])->group(function () {
        Route::get('/profile', [ProfileApiController::class, 'show']);
        Route::post('/profile/update', [ProfileApiController::class, 'update']);

        Route::get('/dashboard/stats', [DashboardApiController::class, 'stats']);

        Route::get('/reports/daily', [ReportApiController::class, 'daily']);
        Route::get('/reports/monthly', [ReportApiController::class, 'monthly']);

        Route::get('/documents-library', [DocumentsLibraryApiController::class, 'index']);

        Route::get('/encodages', [EncodageApiController::class, 'index']);
        Route::delete('/encodages/{id}', [EncodageApiController::class, 'destroy'])->whereNumber('id');

        Route::prefix('encodage-workflow')->group(function () {
            Route::get('/doc-types', [EncodageWorkflowApiController::class, 'docTypes']);
            Route::post('/save-image-ocr', [EncodageWorkflowApiController::class, 'saveImageOcr']);
            Route::get('/clients/search', [EncodageWorkflowApiController::class, 'searchClients']);
            Route::post('/save-client', [EncodageWorkflowApiController::class, 'saveClient']);
            Route::post('/save-document', [EncodageWorkflowApiController::class, 'saveDocument']);
            Route::get('/incomplete', [EncodageWorkflowApiController::class, 'incomplete']);
            Route::get('/{id}/ocr-status', [EncodageWorkflowApiController::class, 'ocrStatus'])->whereNumber('id');
            Route::post('/delete-page', [EncodageWorkflowApiController::class, 'deletePage']);
            Route::get('/{id}/recap', [EncodageWorkflowApiController::class, 'recap'])->whereNumber('id');
            Route::get('/{id}/pages', [EncodageWorkflowApiController::class, 'pages'])->whereNumber('id');
            Route::get('/{id}/pages/{pageId}/file', [EncodageWorkflowApiController::class, 'pageFile'])
                ->whereNumber(['id', 'pageId']);
            Route::get('/{id}/resume', [EncodageWorkflowApiController::class, 'resume'])->whereNumber('id');
            Route::post('/{id}/finalize', [EncodageWorkflowApiController::class, 'finalize'])->whereNumber('id');
        });

        Route::post('/clients/search-by-photo', [ClientApiController::class, 'searchByPhoto']);
        Route::post('/clients/check-duplicates', [ClientApiController::class, 'checkDuplicates']);
        Route::get('/clients', [ClientApiController::class, 'index']);
        Route::get('/clients/{id}/photo', [ClientApiController::class, 'photo'])->whereNumber('id');
        Route::get('/clients/{id}', [ClientApiController::class, 'show'])->whereNumber('id');
        Route::post('/clients/save', [ClientApiController::class, 'save']);
        Route::post('/clients/verify-otp', [ClientApiController::class, 'verifyOtp']);
        Route::post('/clients/resend-otp', [ClientApiController::class, 'resendOtp']);
        Route::post('/clients/toggle', [ClientApiController::class, 'toggle']);

        Route::get('/provinces', [GeoApiController::class, 'provinces']);
        Route::get('/villes-by-province', [GeoApiController::class, 'villesByProvince']);
        Route::get('/villes-for-select', [GeoApiController::class, 'villesForSelect']);
        Route::get('/communes-by-ville', [GeoApiController::class, 'communesByVille']);

        Route::middleware('role.admin')->group(function () {
            Route::get('/reports/global', [ReportApiController::class, 'global']);

            Route::get('/users', [UserApiController::class, 'index']);
            Route::post('/users/save', [UserApiController::class, 'save']);
            Route::delete('/users/{id}', [UserApiController::class, 'destroy'])->whereNumber('id');

            Route::get('/docs', [DocApiController::class, 'index']);
            Route::post('/docs/save', [DocApiController::class, 'save']);
            Route::delete('/docs/{id}', [DocApiController::class, 'destroy'])->whereNumber('id');

            Route::get('/communes', [CommuneApiController::class, 'index']);
            Route::post('/communes/save', [CommuneApiController::class, 'save']);
            Route::delete('/communes/{id}', [CommuneApiController::class, 'destroy'])->whereNumber('id');

            Route::get('/villes', [VilleApiController::class, 'index']);
            Route::post('/villes/save', [VilleApiController::class, 'save']);
            Route::delete('/villes/{id}', [VilleApiController::class, 'destroy'])->whereNumber('id');
        });
    });
});
