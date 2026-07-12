<?php

use Illuminate\Support\Facades\Route;
use Modules\Scm\Http\Controllers\ScmWarehouseController;

Route::get('/warehouses', [ScmWarehouseController::class, 'warehouses']);
Route::post('/warehouses', [ScmWarehouseController::class, 'storeWarehouse']);
Route::post('/warehouses/{warehouse}', [ScmWarehouseController::class, 'updateWarehouse']);
Route::delete('/warehouses/{warehouse}', [ScmWarehouseController::class, 'destroyWarehouse']);

Route::get('/stock', [ScmWarehouseController::class, 'stock']);
Route::get('/stock/{warehouseId}/{productId}', [ScmWarehouseController::class, 'stockDetail']);

Route::get('/inbound', [ScmWarehouseController::class, 'inbound']);
Route::get('/inbound/{id}', [ScmWarehouseController::class, 'inboundShow']);
Route::post('/inbound/create', [ScmWarehouseController::class, 'storeInbound']);
Route::post('/inbound', [ScmWarehouseController::class, 'storeInbound']);
Route::post('/inbound/post', [ScmWarehouseController::class, 'postInbound']);

Route::get('/outbound', [ScmWarehouseController::class, 'outbound']);
Route::get('/outbound/{id}', [ScmWarehouseController::class, 'outboundShow']);
Route::post('/outbound/create', [ScmWarehouseController::class, 'storeOutbound']);
Route::post('/outbound', [ScmWarehouseController::class, 'storeOutbound']);
Route::post('/outbound/post', [ScmWarehouseController::class, 'postOutbound']);

Route::get('/audit', [ScmWarehouseController::class, 'audit']);
Route::get('/audit/{id}', [ScmWarehouseController::class, 'auditShow']);
Route::post('/audit/create', [ScmWarehouseController::class, 'storeAudit']);
Route::post('/audit/record', [ScmWarehouseController::class, 'recordAudit']);
Route::post('/audit/complete', [ScmWarehouseController::class, 'completeAudit']);
Route::post('/audit/post', [ScmWarehouseController::class, 'postAudit']);
