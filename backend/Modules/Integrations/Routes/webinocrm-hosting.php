<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\WebinocrmHostingSettingsController;
use Modules\Integrations\Http\Controllers\WebinocrmInfraAuditController;
use Modules\Integrations\Http\Controllers\WebinocrmModuleGitSourceController;
use Modules\Integrations\Http\Controllers\WebinocrmPortainerController;

/*
| /api/webinocrm/v1/hosting/* — auth:sanctum + role:system_manager (except git webhook)
*/

Route::get('/hosting/settings', [WebinocrmHostingSettingsController::class, 'show']);
Route::put('/hosting/settings', [WebinocrmHostingSettingsController::class, 'update']);

Route::get('/hosting/module-git-sources', [WebinocrmModuleGitSourceController::class, 'index']);
Route::post('/hosting/module-git-sources', [WebinocrmModuleGitSourceController::class, 'store']);
Route::patch('/hosting/module-git-sources/{id}', [WebinocrmModuleGitSourceController::class, 'update'])->whereNumber('id');
Route::delete('/hosting/module-git-sources/{id}', [WebinocrmModuleGitSourceController::class, 'destroy'])->whereNumber('id');

Route::get('/hosting/portainer/stacks', [WebinocrmPortainerController::class, 'stacks']);
Route::get('/hosting/portainer/endpoints', [WebinocrmPortainerController::class, 'endpoints']);
Route::post('/hosting/portainer/stacks/{stackId}/{action}', [WebinocrmPortainerController::class, 'stackAction'])
    ->whereNumber('stackId')
    ->whereIn('action', ['start', 'stop']);

Route::get('/hosting/audit-logs', [WebinocrmInfraAuditController::class, 'index']);
