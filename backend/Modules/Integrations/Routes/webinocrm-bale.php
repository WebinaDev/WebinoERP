<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\WebinocrmBaleRestController;

/*
|--------------------------------------------------------------------------
| /api/webinocrm/v1/bale/* (نیاز به auth:sanctum + role:system_manager)
|--------------------------------------------------------------------------
*/

Route::get('/settings', [WebinocrmBaleRestController::class, 'getSettings']);
Route::post('/settings', [WebinocrmBaleRestController::class, 'updateSettings']);

Route::get('/logs', [WebinocrmBaleRestController::class, 'getLogs']);

Route::get('/webhook-url', [WebinocrmBaleRestController::class, 'getWebhookUrl']);
Route::post('/set-webhook', [WebinocrmBaleRestController::class, 'setWebhook']);

Route::post('/diagnostics/webhook-info', [WebinocrmBaleRestController::class, 'diagnosticsWebhookInfo']);
Route::post('/diagnostics/test-log', [WebinocrmBaleRestController::class, 'diagnosticsTestLog']);
Route::get('/diagnostics/stats', [WebinocrmBaleRestController::class, 'diagnosticsStats']);

Route::post('/message', [WebinocrmBaleRestController::class, 'sendMessage']);
Route::post('/message/bulk', [WebinocrmBaleRestController::class, 'sendBulkMessage']);

Route::get('/stats', [WebinocrmBaleRestController::class, 'getStats']);
Route::get('/user-logs', [WebinocrmBaleRestController::class, 'getUserLogs']);

Route::get('/campaigns', [WebinocrmBaleRestController::class, 'listCampaigns']);
Route::post('/campaigns', [WebinocrmBaleRestController::class, 'createCampaign']);
Route::post('/campaigns/{id}/run', [WebinocrmBaleRestController::class, 'runCampaign'])->whereNumber('id');

Route::get('/kpi', [WebinocrmBaleRestController::class, 'kpiDashboard']);
Route::post('/automation/process-queue', [WebinocrmBaleRestController::class, 'processAutomationQueue']);
