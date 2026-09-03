<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\ApiTokenController;
use Modules\Platform\Http\Controllers\BackupController;
use Modules\Platform\Http\Controllers\CrmSitesController;
use Modules\Platform\Http\Controllers\DashboardController;
use Modules\Platform\Http\Controllers\DomainController;
use Modules\Platform\Http\Controllers\NotificationChannelController;
use Modules\Platform\Http\Controllers\ProjectController;
use Modules\Platform\Http\Controllers\ResourceController;
use Modules\Platform\Http\Controllers\ServerController;
use Modules\Platform\Http\Controllers\ServiceTemplateController;
use Modules\Platform\Http\Controllers\SettingsController;
use Modules\Platform\Http\Controllers\SharedVariableController;
use Modules\Platform\Http\Controllers\SourceController;
use Modules\Platform\Http\Controllers\SshKeyController;
use Modules\Platform\Http\Controllers\StorageController;
use Modules\Platform\Http\Controllers\TagController;
use Modules\Platform\Http\Controllers\WebinoProvisionController;

Route::get('dashboard', [DashboardController::class, 'summary']);

Route::get('servers', [ServerController::class, 'index']);
Route::post('servers', [ServerController::class, 'store']);
Route::get('servers/{server}', [ServerController::class, 'show']);
Route::patch('servers/{server}', [ServerController::class, 'update']);
Route::delete('servers/{server}', [ServerController::class, 'destroy']);
Route::post('servers/{server}/validate', [ServerController::class, 'validateServer']);
Route::post('servers/{server}/bootstrap', [ServerController::class, 'bootstrap']);
Route::get('servers/{server}/resources', [ServerController::class, 'resources']);
Route::get('servers/{server}/images', [ServerController::class, 'images']);
Route::post('servers/{server}/images/pull', [ServerController::class, 'pullImage']);
Route::post('servers/{server}/images/delete', [ServerController::class, 'deleteImage']);
Route::post('servers/{server}/containers/{container}', [ServerController::class, 'containerAction']);
Route::get('servers/{server}/containers/{container}/logs', [ServerController::class, 'containerLogs']);
Route::get('servers/{server}/networks', [ServerController::class, 'networks']);
Route::post('servers/{server}/networks', [ServerController::class, 'createNetwork']);
Route::get('servers/{server}/metrics', [ServerController::class, 'metrics']);
Route::post('servers/{server}/cleanup', [ServerController::class, 'cleanup']);
Route::get('servers/{server}/proxy', [ServerController::class, 'proxy']);
Route::post('servers/{server}/proxy/reload', [ServerController::class, 'proxyReload']);
Route::get('servers/{server}/destinations', [ServerController::class, 'destinations']);
Route::delete('servers/{server}/destinations/{destination}', [ServerController::class, 'destroyDestination']);

// Terminal is RCE-capable — system_manager only (not module.permission alone).
Route::post('servers/{server}/terminal', [ServerController::class, 'terminalExec'])
    ->middleware('role:system_manager');

Route::apiResource('ssh-keys', SshKeyController::class)->only(['index', 'store', 'destroy']);
Route::apiResource('tokens', ApiTokenController::class)->only(['index', 'store', 'destroy']);

Route::get('projects', [ProjectController::class, 'index']);
Route::post('projects', [ProjectController::class, 'store']);
Route::get('projects/{project}', [ProjectController::class, 'show']);
Route::patch('projects/{project}', [ProjectController::class, 'update']);
Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
Route::post('projects/{project}/environments', [ProjectController::class, 'storeEnvironment']);

Route::get('resources', [ResourceController::class, 'index']);
Route::post('resources', [ResourceController::class, 'store']);
Route::get('resources/{resource}', [ResourceController::class, 'show']);
Route::patch('resources/{resource}', [ResourceController::class, 'update']);
Route::delete('resources/{resource}', [ResourceController::class, 'destroy']);
Route::post('resources/{resource}/deploy', [ResourceController::class, 'deploy']);
Route::get('resources/{resource}/deployments', [ResourceController::class, 'deployments']);
Route::put('resources/{resource}/env', [ResourceController::class, 'syncEnv']);
Route::put('resources/{resource}/volumes', [ResourceController::class, 'syncVolumes']);
Route::post('resources/{resource}/start', [ResourceController::class, 'start']);
Route::post('resources/{resource}/stop', [ResourceController::class, 'stop']);
Route::post('resources/{resource}/clone', [ResourceController::class, 'cloneResource']);
Route::post('resources/{resource}/move', [ResourceController::class, 'move']);
Route::post('resources/{resource}/webhook', [ResourceController::class, 'ensureWebhook']);
Route::post('resources/{resource}/domains', [DomainController::class, 'store']);
Route::delete('domains/{domain}', [DomainController::class, 'destroy']);
Route::post('domains/{domain}/ssl/refresh', [DomainController::class, 'refreshSsl']);

Route::get('storages', [StorageController::class, 'index']);
Route::post('storages', [StorageController::class, 'store']);
Route::delete('storages/{storage}', [StorageController::class, 'destroy']);

Route::get('backups', [BackupController::class, 'index']);
Route::get('backup-schedules', [BackupController::class, 'schedules']);
Route::post('backup-schedules', [BackupController::class, 'storeSchedule']);
Route::post('backups/run', [BackupController::class, 'run']);
Route::post('backups/{backup}/restore', [BackupController::class, 'restore']);

Route::get('sources', [SourceController::class, 'index']);
Route::post('sources', [SourceController::class, 'store']);
Route::delete('sources/{source}', [SourceController::class, 'destroy']);

Route::get('notifications', [NotificationChannelController::class, 'index']);
Route::post('notifications', [NotificationChannelController::class, 'store']);
Route::post('notifications/{channel}/test', [NotificationChannelController::class, 'test']);
Route::delete('notifications/{channel}', [NotificationChannelController::class, 'destroy']);

Route::get('services/templates', [ServiceTemplateController::class, 'index']);
Route::get('services/templates/{slug}', [ServiceTemplateController::class, 'show']);

Route::get('settings', [SettingsController::class, 'show']);
Route::put('settings', [SettingsController::class, 'update']);

Route::get('variables', [SharedVariableController::class, 'index']);
Route::post('variables', [SharedVariableController::class, 'store']);
Route::delete('variables/{variable}', [SharedVariableController::class, 'destroy']);

Route::get('tags', [TagController::class, 'index']);
Route::post('tags', [TagController::class, 'store']);

Route::post('webino/launch', [WebinoProvisionController::class, 'launch']);
Route::get('crm/{accountId}/sites', [CrmSitesController::class, 'show']);
