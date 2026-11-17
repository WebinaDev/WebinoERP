<?php

use Illuminate\Support\Facades\Route;
use Modules\Bi\Http\Controllers\BiController;

Route::get('/kpis', [BiController::class, 'kpis']);
Route::get('/dashboards/{role}', [BiController::class, 'dashboard'])->whereAlpha('role');
Route::get('/export', [BiController::class, 'export']);

Route::get('/subscriptions', [BiController::class, 'subscriptions']);
Route::post('/subscriptions', [BiController::class, 'storeSubscription']);

Route::get('/alerts', [BiController::class, 'alerts']);
Route::post('/alerts', [BiController::class, 'storeAlert']);
