<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\AuthParityController;
use Modules\Core\Http\Controllers\ActivityController;
use Modules\Core\Http\Controllers\AnalyticsController;
use Modules\Core\Http\Controllers\AutomationRuleController;
use Modules\Core\Http\Controllers\BrandingController;
use Modules\Core\Http\Controllers\ChatController;
use Modules\Core\Http\Controllers\CoreSearchController;
use Modules\Core\Http\Controllers\DocumentController;
use Modules\Core\Http\Controllers\CrudParityController;
use Modules\Core\Http\Controllers\DashboardParityController;
use Modules\Core\Http\Controllers\DashboardStatsController;
use Modules\Core\Http\Controllers\FieldPermissionsController;
use Modules\Core\Http\Controllers\HealthReadinessController;
use Modules\Core\Http\Controllers\LicenseParityController;
use Modules\Core\Http\Controllers\MaintenanceParityController;
use Modules\Core\Http\Controllers\LogParityController;
use Modules\Core\Http\Controllers\NavigationController;
use Modules\Core\Http\Controllers\PwaManifestController;
use Modules\Core\Http\Controllers\ReportsController;
use Modules\Core\Http\Controllers\ReminderController;
use Modules\Core\Http\Controllers\SettingsParityController;
use Modules\Core\Http\Controllers\SessionController;
use Modules\Core\Http\Controllers\SystemConfigController;
use Modules\Core\Http\Controllers\SystemLogController;
use Modules\Core\Http\Controllers\TwoFactorController;
use Modules\Core\Http\Controllers\VisitorStatsController;

/*
|--------------------------------------------------------------------------
| API Routes — Core /api/v1/core
|--------------------------------------------------------------------------
*/

Route::get('/health/readiness', [HealthReadinessController::class, 'readiness']);
Route::get('/health/metrics', [HealthReadinessController::class, 'metrics']);

Route::get('/config', [SystemConfigController::class, 'getConfig']);
Route::get('/branding.css', [BrandingController::class, 'css']);
Route::get('/branding', [BrandingController::class, 'manifest']);
Route::get('/manifest.json', [PwaManifestController::class, 'json']);

Route::middleware('throttle:auth-public')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/otp/send', [AuthParityController::class, 'sendLoginOtp']);
    Route::post('/auth/otp/verify', [AuthParityController::class, 'verifyLoginOtp']);
    Route::post('/auth/password/set', [AuthParityController::class, 'setPassword']);
    Route::post('/auth/auto-login', [AuthParityController::class, 'autoLogin']);
    Route::post('/auth/email-otp/send', [AuthParityController::class, 'sendEmailOtp']);
    Route::post('/auth/email-otp/verify', [AuthParityController::class, 'verifyEmailOtp']);
    Route::post('/auth/register', [AuthParityController::class, 'register']);
});

Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/2fa/status', [TwoFactorController::class, 'status']);
    Route::post('/auth/2fa/send', [TwoFactorController::class, 'send']);
    Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('/auth/auto-login/issue', [AuthParityController::class, 'issueAutoLoginToken']);
});

Route::post('/visitor-stats/track', [VisitorStatsController::class, 'track']);

Route::middleware(['auth:sanctum', 'module.permission:core'])->group(function () {
    Route::get('/navigation', NavigationController::class);
    Route::get('/dashboard/stats', DashboardStatsController::class);
    Route::get('/dashboard', [DashboardParityController::class, 'full']);
    Route::get('/dashboard/stats/team-member', [DashboardParityController::class, 'teamMemberStats']);
    Route::get('/dashboard/stats/client', [DashboardParityController::class, 'clientStats']);

    Route::get('/settings', [SettingsParityController::class, 'show']);
    Route::put('/settings', [SettingsParityController::class, 'update']);
    Route::put('/settings/white-label', [SettingsParityController::class, 'updateWhiteLabel']);
    Route::put('/settings/auth', [SettingsParityController::class, 'updateAuth']);

    Route::get('/users/me/preferences', [CrudParityController::class, 'getPreferences']);
    Route::patch('/users/me/preferences', [CrudParityController::class, 'preferences']);
    Route::patch('/users/me', [CrudParityController::class, 'meUpdate']);
    Route::post('/users/me/avatar', [CrudParityController::class, 'avatarUpload']);
    Route::get('/users', [CrudParityController::class, 'usersIndex'])->middleware('fieldsec:user');
    Route::post('/users', [CrudParityController::class, 'usersStore'])->middleware('fieldsec:user');
    Route::patch('/users/{id}', [CrudParityController::class, 'usersUpdate'])->middleware('fieldsec:user')->whereNumber('id');
    Route::delete('/users/{id}', [CrudParityController::class, 'usersDestroy'])->middleware('fieldsec:user')->whereNumber('id');
    Route::get('/users/search', [CrudParityController::class, 'usersSearch']);

    Route::get('/canned-responses', [CrudParityController::class, 'cannedIndex']);
    Route::post('/canned-responses', [CrudParityController::class, 'cannedStore']);
    Route::put('/canned-responses/{id}', [CrudParityController::class, 'cannedUpdate'])->whereNumber('id');
    Route::delete('/canned-responses/{id}', [CrudParityController::class, 'cannedDestroy'])->whereNumber('id');
    Route::get('/canned-responses/{id}', [CrudParityController::class, 'cannedShow'])->whereNumber('id');

    Route::get('/positions', [CrudParityController::class, 'positionsIndex']);
    Route::post('/positions', [CrudParityController::class, 'positionsStore']);
    Route::put('/positions/{id}', [CrudParityController::class, 'positionsUpdate'])->whereNumber('id');
    Route::delete('/positions/{id}', [CrudParityController::class, 'positionsDestroy'])->whereNumber('id');

    Route::get('/task-categories', [CrudParityController::class, 'taskCategoriesIndex']);
    Route::post('/task-categories', [CrudParityController::class, 'taskCategoriesStore']);
    Route::put('/task-categories/{id}', [CrudParityController::class, 'taskCategoriesUpdate'])->whereNumber('id');
    Route::delete('/task-categories/{id}', [CrudParityController::class, 'taskCategoriesDestroy'])->whereNumber('id');

    Route::get('/notifications', [CrudParityController::class, 'notificationsIndex']);
    Route::post('/notifications', [CrudParityController::class, 'notificationStore']);
    Route::patch('/notifications/{id}', [CrudParityController::class, 'notificationUpdate'])->whereNumber('id');
    Route::delete('/notifications/{id}', [CrudParityController::class, 'notificationDestroy'])->whereNumber('id');
    Route::patch('/notifications/{id}/read', [CrudParityController::class, 'notificationRead'])->whereNumber('id');
    Route::post('/notifications/{id}/read', [CrudParityController::class, 'notificationRead'])->whereNumber('id');

    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::patch('/reminders/{id}/snooze', [ReminderController::class, 'snooze'])->whereNumber('id');
    Route::patch('/reminders/{id}/dismiss', [ReminderController::class, 'dismiss'])->whereNumber('id');
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->whereNumber('id');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->whereNumber('id');
    Route::post('/documents/folders', [DocumentController::class, 'createFolder']);
    Route::patch('/documents/{id}/rename', [DocumentController::class, 'rename'])->whereNumber('id');
    Route::patch('/documents/{id}/move', [DocumentController::class, 'move'])->whereNumber('id');
    Route::post('/documents/{id}/share', [DocumentController::class, 'share'])->whereNumber('id');
    Route::get('/documents/{id}/versions', [DocumentController::class, 'versions'])->whereNumber('id');
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->whereNumber('id');
    Route::delete('/sessions/all-others', [SessionController::class, 'destroyOthers']);

    Route::prefix('chat')->group(function () {
        Route::get('channels', [ChatController::class, 'channels']);
        Route::post('channels', [ChatController::class, 'storeChannel']);
        Route::get('channels/{id}/messages', [ChatController::class, 'messages'])->whereNumber('id');
        Route::post('channels/{id}/read', [ChatController::class, 'read'])->whereNumber('id');
        Route::post('channels/{id}/typing', [ChatController::class, 'typing'])->whereNumber('id');
        Route::get('direct/{user_id}', [ChatController::class, 'direct'])->whereNumber('user_id');
        Route::get('unread-count', [ChatController::class, 'unreadCount']);
        Route::get('messages/search', [ChatController::class, 'searchMessages']);
        Route::post('messages', [ChatController::class, 'storeMessage']);
        Route::delete('messages/{id}', [ChatController::class, 'destroyMessage'])->whereNumber('id');
    });

    Route::get('/automation/rules', [AutomationRuleController::class, 'index']);
    Route::post('/automation/rules', [AutomationRuleController::class, 'store']);
    Route::patch('/automation/rules/{id}', [AutomationRuleController::class, 'update'])->whereNumber('id');
    Route::delete('/automation/rules/{id}', [AutomationRuleController::class, 'destroy'])->whereNumber('id');
    Route::post('/automation/rules/{id}/execute', [AutomationRuleController::class, 'execute'])->whereNumber('id');
    Route::post('/automation/trigger', [AutomationRuleController::class, 'trigger']);

    Route::get('/field-permissions', [FieldPermissionsController::class, 'index']);
    Route::put('/field-permissions', [FieldPermissionsController::class, 'update']);
    Route::delete('/field-permissions/{id}', [FieldPermissionsController::class, 'destroy'])->whereNumber('id');
    Route::get('/field-permissions/viewable', [FieldPermissionsController::class, 'viewable']);

    Route::get('/logs', [DashboardParityController::class, 'logs']);
    Route::get('/reports', [ReportsController::class, 'index']);
    Route::get('/reports/export.csv', [ReportsController::class, 'exportCsv']);
    Route::get('/logs/user', [DashboardParityController::class, 'userLogs']);
    Route::post('/logs/console', [LogParityController::class, 'console']);
    Route::post('/logs/user-actions', [LogParityController::class, 'userAction']);
    Route::delete('/logs/system', [LogParityController::class, 'deleteSystem']);

    Route::middleware('role:system_manager')->group(function () {
        Route::get('/licenses', [LicenseParityController::class, 'index']);
        Route::post('/licenses', [LicenseParityController::class, 'store']);
        Route::patch('/licenses/{id}', [LicenseParityController::class, 'update'])->whereNumber('id');
        Route::post('/licenses/{id}/renew', [LicenseParityController::class, 'renew'])->whereNumber('id');
        Route::post('/licenses/{id}/cancel', [LicenseParityController::class, 'cancel'])->whereNumber('id');
        Route::delete('/licenses/{id}', [LicenseParityController::class, 'destroy'])->whereNumber('id');
    });

    Route::get('/files/pdf/{token}', [CrudParityController::class, 'pdfByToken']);
    Route::get('/visitor-stats', [VisitorStatsController::class, 'index']);
    Route::get('/search', [CoreSearchController::class, 'search']);
    Route::get('/analytics/kpi', [AnalyticsController::class, 'kpi']);
    Route::get('/analytics/funnel', [AnalyticsController::class, 'funnel']);
    Route::get('/analytics/cohort', [AnalyticsController::class, 'cohort']);
    Route::post('/maintenance/optimize', [MaintenanceParityController::class, 'optimize']);
    Route::post('/maintenance/cache/clear', [MaintenanceParityController::class, 'cacheClear']);
    Route::get('/maintenance/cache/stats', [MaintenanceParityController::class, 'cacheStats']);
    Route::get('/logs/system', [SystemLogController::class, 'index']);
});

