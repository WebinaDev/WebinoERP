<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\BaleIntegrationController;
use Modules\Integrations\Http\Controllers\ModirPayamakAdminController;
use Modules\Integrations\Http\Controllers\ModirPayamakCustomerController;
use Modules\Integrations\Http\Controllers\ModirPayamakSettingsController;
use Modules\Integrations\Http\Controllers\PaymentIntegrationController;
use Modules\Integrations\Http\Controllers\SmsIntegrationController;
use Modules\Integrations\Http\Controllers\TelegramIntegrationController;

Route::get('/sms/settings', [SmsIntegrationController::class, 'getSettings'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/sms/send', [SmsIntegrationController::class, 'send'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::put('/sms/settings', [SmsIntegrationController::class, 'updateSettings'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);

Route::post('/payments/initiate', [PaymentIntegrationController::class, 'initiate'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/payments/verify', [PaymentIntegrationController::class, 'verify']);

Route::post('/bale/messages', [BaleIntegrationController::class, 'sendMessage'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/bale/messages/bulk', [BaleIntegrationController::class, 'sendBulkMessage'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/bale/webhook', [BaleIntegrationController::class, 'webhook']);

Route::post('/telegram/webhook', [TelegramIntegrationController::class, 'webhook']);
Route::post('/telegram/send', [TelegramIntegrationController::class, 'send'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);

Route::prefix('modirpayamak')->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations'])->group(function () {
    // Customer API
    Route::get('/account', [ModirPayamakCustomerController::class, 'account']);
    Route::get('/packages', [ModirPayamakCustomerController::class, 'packages']);
    Route::post('/topup/init', [ModirPayamakCustomerController::class, 'topupInit']);
    Route::post('/topup/verify', [ModirPayamakCustomerController::class, 'topupVerify']);
    Route::post('/send', [ModirPayamakCustomerController::class, 'send']);
    Route::post('/send/peer-to-peer', [ModirPayamakCustomerController::class, 'sendPeerToPeer']);
    Route::post('/send/calculate-price', [ModirPayamakCustomerController::class, 'calculatePrice']);
    Route::get('/reports/outbox', [ModirPayamakCustomerController::class, 'reportsOutbox']);
    Route::get('/reports/outbox/{id}', [ModirPayamakCustomerController::class, 'reportOutboxDetail']);
    Route::get('/reports/messages', [ModirPayamakCustomerController::class, 'reportsMessages']);
    Route::get('/patterns', [ModirPayamakCustomerController::class, 'patterns']);
    Route::get('/numbers', [ModirPayamakCustomerController::class, 'numbers']);
    Route::match(['get', 'post'], '/phonebooks', [ModirPayamakCustomerController::class, 'phonebooks']);
    Route::match(['get', 'post'], '/phonebooks/{id}/contacts', [ModirPayamakCustomerController::class, 'phonebookContacts']);

    // Admin API (dashboard ERP pages)
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [ModirPayamakAdminController::class, 'dashboard']);
        Route::post('/proxy', [ModirPayamakAdminController::class, 'proxy']);
        Route::get('/customers', [ModirPayamakAdminController::class, 'customers']);
        Route::post('/customers/balance', [ModirPayamakAdminController::class, 'customerBalance']);
        Route::get('/packages', [ModirPayamakAdminController::class, 'packagesIndex']);
        Route::post('/packages', [ModirPayamakAdminController::class, 'packagesStore']);
        Route::delete('/packages/{package}', [ModirPayamakAdminController::class, 'packagesDestroy']);
        Route::get('/orders', [ModirPayamakAdminController::class, 'orders']);
        Route::post('/send', [ModirPayamakAdminController::class, 'adminSend']);
        Route::get('/messages', [ModirPayamakAdminController::class, 'messages']);
    });

    // Settings
    Route::get('/settings', [ModirPayamakSettingsController::class, 'show']);
    Route::put('/settings', [ModirPayamakSettingsController::class, 'update']);

    // Legacy list aliases for EntityCrudPage sub-routes
    Route::get('/customers', [ModirPayamakAdminController::class, 'customers']);
    Route::get('/orders', [ModirPayamakAdminController::class, 'orders']);
    Route::get('/reports', [ModirPayamakCustomerController::class, 'reportsOutbox']);
    Route::get('/send', fn () => response()->json(['data' => ['hint' => 'POST to /modirpayamak/send']]));
});
