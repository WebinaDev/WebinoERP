<?php

use Illuminate\Support\Facades\Route;
use Modules\SiteBuilder\Http\Controllers\BusinessCategoryController;
use Modules\SiteBuilder\Http\Controllers\BusinessTypeController;
use Modules\SiteBuilder\Http\Controllers\DashboardFeatureController;
use Modules\SiteBuilder\Http\Controllers\PackageController;
use Modules\SiteBuilder\Http\Controllers\SiteProvisionController;

Route::get('/catalog', [BusinessCategoryController::class, 'index']);

Route::get('/categories', [BusinessCategoryController::class, 'index']);
Route::post('/categories', [BusinessCategoryController::class, 'store']);
Route::get('/categories/{siteCategory}', [BusinessCategoryController::class, 'show']);
Route::patch('/categories/{siteCategory}', [BusinessCategoryController::class, 'update']);
Route::put('/categories/{siteCategory}', [BusinessCategoryController::class, 'update']);
Route::delete('/categories/{siteCategory}', [BusinessCategoryController::class, 'destroy']);

Route::get('/types', [BusinessTypeController::class, 'index']);
Route::post('/types', [BusinessTypeController::class, 'store']);
Route::get('/types/{siteType}', [BusinessTypeController::class, 'show']);
Route::patch('/types/{siteType}', [BusinessTypeController::class, 'update']);
Route::put('/types/{siteType}', [BusinessTypeController::class, 'update']);
Route::delete('/types/{siteType}', [BusinessTypeController::class, 'destroy']);

Route::get('/features', [DashboardFeatureController::class, 'index']);
Route::post('/features', [DashboardFeatureController::class, 'store']);
Route::get('/features/{siteFeature}', [DashboardFeatureController::class, 'show']);
Route::patch('/features/{siteFeature}', [DashboardFeatureController::class, 'update']);
Route::put('/features/{siteFeature}', [DashboardFeatureController::class, 'update']);
Route::delete('/features/{siteFeature}', [DashboardFeatureController::class, 'destroy']);

Route::get('/packages', [PackageController::class, 'index']);
Route::post('/packages', [PackageController::class, 'store']);
Route::get('/packages/{sitePackage}', [PackageController::class, 'show']);
Route::patch('/packages/{sitePackage}', [PackageController::class, 'update']);
Route::put('/packages/{sitePackage}', [PackageController::class, 'update']);
Route::delete('/packages/{sitePackage}', [PackageController::class, 'destroy']);

Route::get('/provisions', [SiteProvisionController::class, 'index']);
Route::post('/provisions', [SiteProvisionController::class, 'store']);
Route::get('/provisions/{siteProvision}', [SiteProvisionController::class, 'show']);
Route::get('/provisions/{siteProvision}/control', [SiteProvisionController::class, 'control']);
Route::patch('/provisions/{siteProvision}', [SiteProvisionController::class, 'update']);
Route::post('/provisions/{siteProvision}/prepare-license', [SiteProvisionController::class, 'prepareLicense']);
Route::post('/provisions/{siteProvision}/launch', [SiteProvisionController::class, 'launch'])->middleware('throttle:10,1');
Route::get('/provisions/{siteProvision}/status', [SiteProvisionController::class, 'status']);
Route::post('/provisions/{siteProvision}/cancel', [SiteProvisionController::class, 'cancel'])->middleware('throttle:10,1');
Route::post('/provisions/{siteProvision}/retry', [SiteProvisionController::class, 'retry'])->middleware('throttle:10,1');
Route::post('/provisions/{siteProvision}/start', [SiteProvisionController::class, 'start']);
Route::post('/provisions/{siteProvision}/stop', [SiteProvisionController::class, 'stop']);
Route::get('/provisions/{siteProvision}/logs', [SiteProvisionController::class, 'logs']);
Route::post('/provisions/{siteProvision}/admin', [SiteProvisionController::class, 'updateAdmin']);
Route::post('/provisions/{siteProvision}/modules', [SiteProvisionController::class, 'updateModules']);
Route::post('/provisions/{siteProvision}/channel', [SiteProvisionController::class, 'setChannel']);
Route::post('/provisions/{siteProvision}/update', [SiteProvisionController::class, 'queueUpdate'])->middleware('throttle:10,1');
Route::post('/provisions/{siteProvision}/ssl/renew', [SiteProvisionController::class, 'renewSsl'])->middleware('throttle:10,1');
Route::delete('/provisions/{siteProvision}', [SiteProvisionController::class, 'destroy']);
