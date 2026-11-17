<?php

use Illuminate\Support\Facades\Route;
use Modules\Qm\Http\Controllers\QmController;

Route::get('/plans', [QmController::class, 'plans']);
Route::post('/plans', [QmController::class, 'storePlan']);

Route::get('/lots', [QmController::class, 'lotBatches']);
Route::post('/lots', [QmController::class, 'storeLotBatch']);
Route::patch('/lots/{id}/status', [QmController::class, 'updateLotStatus'])->whereNumber('id');

Route::get('/inspections', [QmController::class, 'inspections']);
Route::post('/inspections', [QmController::class, 'storeInspection']);

Route::get('/ncrs', [QmController::class, 'ncrs']);
Route::post('/ncrs', [QmController::class, 'storeNcr']);

Route::get('/capas', [QmController::class, 'capas']);
Route::post('/capas', [QmController::class, 'storeCapa']);

Route::get('/analytics', [QmController::class, 'analytics']);
