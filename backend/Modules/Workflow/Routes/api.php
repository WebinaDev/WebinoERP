<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Http\Controllers\WorkflowController;

Route::get('/definitions', [WorkflowController::class, 'definitions']);
Route::post('/definitions', [WorkflowController::class, 'storeDefinition']);
Route::get('/definitions/{id}', [WorkflowController::class, 'showDefinition'])->whereNumber('id');

Route::get('/inbox', [WorkflowController::class, 'inbox']);
Route::post('/approvals/{id}/decide', [WorkflowController::class, 'decide'])->whereNumber('id');

Route::get('/delegations', [WorkflowController::class, 'delegations']);
Route::post('/delegations', [WorkflowController::class, 'storeDelegation']);

Route::get('/history', [WorkflowController::class, 'history']);
Route::post('/initiate', [WorkflowController::class, 'initiate']);
