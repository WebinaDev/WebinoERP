<?php

use Illuminate\Support\Facades\Route;
use Modules\SiteBuilder\Http\Controllers\BusinessCategoryController;
use Modules\SiteBuilder\Http\Controllers\BusinessTypeController;
use Modules\SiteBuilder\Http\Controllers\DashboardFeatureController;
use Modules\SiteBuilder\Http\Controllers\PackageController;
use Modules\SiteBuilder\Http\Controllers\SiteProvisionController;

Route::get('/catalog', [BusinessCategoryController::class, 'index']);

Route::apiResource('categories', BusinessCategoryController::class);
Route::apiResource('types', BusinessTypeController::class);
Route::apiResource('features', DashboardFeatureController::class);
Route::apiResource('packages', PackageController::class);

Route::get('/provisions', [SiteProvisionController::class, 'index']);
Route::post('/provisions', [SiteProvisionController::class, 'store']);
Route::get('/provisions/{siteProvision}', [SiteProvisionController::class, 'show']);
Route::patch('/provisions/{siteProvision}', [SiteProvisionController::class, 'update']);
Route::post('/provisions/{siteProvision}/prepare-license', [SiteProvisionController::class, 'prepareLicense']);
Route::post('/provisions/{siteProvision}/launch', [SiteProvisionController::class, 'launch'])->middleware('throttle:10,1');
Route::get('/provisions/{siteProvision}/status', [SiteProvisionController::class, 'status']);
Route::post('/provisions/{siteProvision}/retry', [SiteProvisionController::class, 'retry'])->middleware('throttle:10,1');
Route::delete('/provisions/{siteProvision}', [SiteProvisionController::class, 'destroy']);
