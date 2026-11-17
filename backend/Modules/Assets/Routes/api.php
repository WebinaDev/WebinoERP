<?php

use Illuminate\Support\Facades\Route;
use Modules\Assets\Http\Controllers\AssetsController;

Route::get('/items', [AssetsController::class, 'assets']);
Route::post('/items', [AssetsController::class, 'storeAsset']);
Route::get('/items/{id}', [AssetsController::class, 'showAsset'])->whereNumber('id');

Route::get('/depreciation-runs', [AssetsController::class, 'depreciationRuns']);
Route::post('/depreciation-runs', [AssetsController::class, 'runDepreciation']);

Route::get('/maintenance-plans', [AssetsController::class, 'maintenancePlans']);
Route::post('/maintenance-plans', [AssetsController::class, 'storeMaintenancePlan']);

Route::get('/maintenance-orders', [AssetsController::class, 'maintenanceOrders']);
Route::post('/maintenance-orders', [AssetsController::class, 'storeMaintenanceOrder']);
Route::post('/maintenance-orders/{id}/complete', [AssetsController::class, 'completeMaintenanceOrder'])->whereNumber('id');

Route::get('/insurances', [AssetsController::class, 'insurances']);
Route::post('/insurances', [AssetsController::class, 'storeInsurance']);
