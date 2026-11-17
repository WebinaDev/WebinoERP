<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldService\Http\Controllers\FieldServiceController;

Route::get('/install-bases', [FieldServiceController::class, 'installBases']);
Route::post('/install-bases', [FieldServiceController::class, 'storeInstallBase']);
Route::get('/install-bases/{id}', [FieldServiceController::class, 'showInstallBase'])->whereNumber('id');

Route::get('/technicians', [FieldServiceController::class, 'technicians']);
Route::post('/technicians', [FieldServiceController::class, 'storeTechnician']);

Route::get('/orders', [FieldServiceController::class, 'orders']);
Route::post('/orders', [FieldServiceController::class, 'storeOrder']);
Route::post('/orders/{id}/dispatch', [FieldServiceController::class, 'dispatchOrder'])->whereNumber('id');
Route::post('/orders/{id}/spare-parts', [FieldServiceController::class, 'addSparePart'])->whereNumber('id');
Route::post('/orders/{id}/complete', [FieldServiceController::class, 'completeOrder'])->whereNumber('id');

Route::get('/reports', [FieldServiceController::class, 'reports']);
