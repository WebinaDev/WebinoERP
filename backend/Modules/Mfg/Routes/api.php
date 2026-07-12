<?php

use Illuminate\Support\Facades\Route;
use Modules\Mfg\Http\Controllers\BomController;
use Modules\Mfg\Http\Controllers\OverviewController;
use Modules\Mfg\Http\Controllers\PlanningController;
use Modules\Mfg\Http\Controllers\QualityInspectionController;
use Modules\Mfg\Http\Controllers\WorkOrderController;

Route::get('/overview', [OverviewController::class, 'index']);

Route::apiResource('boms', BomController::class);
Route::get('boms/{bom}/lines', [BomController::class, 'lines']);

Route::apiResource('work-orders', WorkOrderController::class);
Route::post('work-orders/{work_order}/release', [WorkOrderController::class, 'release']);
Route::post('work-orders/{work_order}/start', [WorkOrderController::class, 'start']);
Route::post('work-orders/{work_order}/complete', [WorkOrderController::class, 'complete']);
Route::post('work-orders/{work_order}/cancel', [WorkOrderController::class, 'cancel']);

Route::apiResource('inspections', QualityInspectionController::class);
Route::post('inspections/{inspection}/complete', [QualityInspectionController::class, 'complete']);

Route::get('planning/mrp', [PlanningController::class, 'mrp']);
