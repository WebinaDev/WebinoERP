<?php

use Illuminate\Support\Facades\Route;
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
Route::get('gitea/settings', [GiteaController::class, 'settings']);
Route::put('gitea/settings', [GiteaController::class, 'updateSettings']);
Route::post('gitea/test', [GiteaController::class, 'testConnection']);

Route::apiResource('modules', ModuleController::class);
Route::post('modules/{module}/repo', [ModuleController::class, 'attachRepo']);
Route::post('modules/{module}/repo/sync', [ModuleController::class, 'syncRepo']);
Route::patch('modules/{module}/repo', [ModuleController::class, 'patchRepo']);
Route::post('modules/{module}/readme/sync', [ModuleController::class, 'syncReadme']);
Route::get('modules/{module}/releases', [ModuleController::class, 'releasesIndex']);
Route::post('modules/{module}/releases', [ModuleController::class, 'releasesStore']);
Route::post('releases/{release}/publish', [ReleaseController::class, 'publish']);
Route::delete('releases/{release}', [ReleaseController::class, 'destroy']);
