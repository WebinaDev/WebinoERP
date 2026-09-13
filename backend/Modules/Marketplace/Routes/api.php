<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\BasalamConnectionsController;
use Modules\Marketplace\Http\Controllers\BasalamOAuthController;
use Modules\Marketplace\Http\Controllers\CategoryController;
use Modules\Marketplace\Http\Controllers\GiteaController;
use Modules\Marketplace\Http\Controllers\ModuleController;
use Modules\Marketplace\Http\Controllers\OrderController;
use Modules\Marketplace\Http\Controllers\ProductController;
use Modules\Marketplace\Http\Controllers\ReleaseController;
use Modules\Marketplace\Http\Controllers\SiteThemeController;

Route::apiResource('products', ProductController::class);
Route::get('themes', [SiteThemeController::class, 'index']);
Route::apiResource('categories', CategoryController::class)->except(['show']);
Route::apiResource('orders', OrderController::class);
Route::post('orders/purchase', [OrderController::class, 'purchase']);
Route::post('orders/{order}/grant-license', [OrderController::class, 'grantLicense']);
Route::match(['get', 'post'], 'orders/{order}/payment/callback', [OrderController::class, 'paymentCallback'])
    ->withoutMiddleware(['auth:sanctum', 'module:marketplace', 'module.permission:marketplace']);

Route::get('gitea/settings', [GiteaController::class, 'settings']);
Route::put('gitea/settings', [GiteaController::class, 'updateSettings']);
Route::post('gitea/settings', [GiteaController::class, 'updateSettings']);
Route::post('gitea/test', [GiteaController::class, 'testConnection']);
Route::get('org-git/settings', [GiteaController::class, 'settings']);
Route::put('org-git/settings', [GiteaController::class, 'updateSettings']);
Route::post('org-git/settings', [GiteaController::class, 'updateSettings']);
Route::post('org-git/test', [GiteaController::class, 'testConnection']);
Route::get('org-git/repos', [GiteaController::class, 'listRepos']);
// Compat aliases used by older frontend paths
Route::get('gitea', [GiteaController::class, 'settings']);
Route::put('gitea', [GiteaController::class, 'updateSettings']);
Route::post('gitea', [GiteaController::class, 'updateSettings']);

Route::apiResource('modules', ModuleController::class);
Route::post('modules/{module}/repo', [ModuleController::class, 'attachRepo']);
Route::post('modules/{module}/repo/sync', [ModuleController::class, 'syncRepo']);
Route::patch('modules/{module}/repo', [ModuleController::class, 'patchRepo']);
Route::post('modules/{module}/readme/sync', [ModuleController::class, 'syncReadme']);
Route::get('modules/{module}/releases', [ModuleController::class, 'releasesIndex']);
Route::post('modules/{module}/releases', [ModuleController::class, 'releasesStore']);
Route::post('releases/{release}/publish', [ReleaseController::class, 'publish']);
Route::delete('releases/{release}', [ReleaseController::class, 'destroy']);

// Basalam admin (authenticated)
Route::get('basalam/oauth/status', [BasalamOAuthController::class, 'status']);
Route::post('basalam/oauth/status', [BasalamOAuthController::class, 'status']);
Route::get('basalam/oauth/config', [BasalamOAuthController::class, 'getConfig']);
Route::post('basalam/oauth/config', [BasalamOAuthController::class, 'saveConfig']);
Route::get('basalam/connections', [BasalamConnectionsController::class, 'index']);
Route::post('basalam/connections/disconnect', [BasalamConnectionsController::class, 'disconnect']);

// Basalam public / rate-limited (merchant-facing)
Route::post('basalam/oauth/start', [BasalamOAuthController::class, 'start'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware(['auth:sanctum', 'module:marketplace', 'module.permission:marketplace']);
Route::post('basalam/oauth/refresh', [BasalamOAuthController::class, 'refresh'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware(['auth:sanctum', 'module:marketplace', 'module.permission:marketplace']);
Route::post('basalam/oauth/disconnect', [BasalamOAuthController::class, 'disconnect'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware(['auth:sanctum', 'module:marketplace', 'module.permission:marketplace']);
