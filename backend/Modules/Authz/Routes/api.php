<?php

use Illuminate\Support\Facades\Route;
use Modules\Authz\Http\Controllers\AuthzController;

Route::get('/me', [AuthzController::class, 'me']);
Route::post('/check', [AuthzController::class, 'check']);
Route::post('/check-bulk', [AuthzController::class, 'checkBulk']);
Route::post('/simulate', [AuthzController::class, 'simulate']);

Route::get('/permissions', [AuthzController::class, 'permissions']);
Route::get('/roles', [AuthzController::class, 'roles']);
Route::post('/roles', [AuthzController::class, 'storeRole']);
Route::get('/roles/{id}', [AuthzController::class, 'showRole'])->whereNumber('id');

Route::get('/field-policies', [AuthzController::class, 'fieldPolicies']);
Route::post('/field-policies', [AuthzController::class, 'storeFieldPolicy']);

Route::get('/audit-logs', [AuthzController::class, 'auditLogs']);
