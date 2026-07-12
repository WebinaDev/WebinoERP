<?php

use Illuminate\Support\Facades\Route;
use Modules\Docs\Http\Controllers\ContractController;
use Modules\Docs\Http\Controllers\FileController;

Route::apiResource('contracts', ContractController::class);
Route::post('contracts/{contract}/cancel', [ContractController::class, 'cancel']);
Route::post('contracts/{contract}/projects', [ContractController::class, 'linkProject']);

Route::get('files', [FileController::class, 'index']);
Route::post('files', [FileController::class, 'store']);
Route::patch('files/{file}', [FileController::class, 'update']);
Route::get('files/{file}/download', [FileController::class, 'download']);
Route::post('files/folders', [FileController::class, 'createFolder']);
Route::post('files/{file}/share', [FileController::class, 'share']);
Route::get('files/{file}/versions', [FileController::class, 'versions']);
Route::delete('files/{file}', [FileController::class, 'destroy']);
