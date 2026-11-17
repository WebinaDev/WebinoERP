<?php

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementController;

Route::get('/requests', [ProcurementController::class, 'requests']);
Route::post('/requests', [ProcurementController::class, 'storeRequest']);

Route::get('/rfqs', [ProcurementController::class, 'rfqs']);
Route::post('/rfqs', [ProcurementController::class, 'storeRfq']);

Route::get('/orders', [ProcurementController::class, 'orders']);
Route::post('/orders', [ProcurementController::class, 'storeOrder']);

Route::get('/receipts', [ProcurementController::class, 'receipts']);
Route::post('/receipts', [ProcurementController::class, 'storeReceipt']);

Route::post('/match-3way', [ProcurementController::class, 'matchThreeWay']);
