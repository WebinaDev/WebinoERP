<?php

use App\Http\Controllers\OpenApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/openapi.json', [OpenApiController::class, 'show']);

    // Public, PII-free license renewal info for tenant Suspended pages / expiring banners.
    Route::get('/public/license-renewal', [\Modules\Core\Http\Controllers\LicenseRenewalPublicController::class, 'info'])
        ->middleware('throttle:license-renewal-public');

    // API v1 routes are handled by modules
    // Core module routes: /api/v1/core/*
    // CRM module routes: /api/v1/crm/*
});

