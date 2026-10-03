<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\BaleIntegrationController;
use Modules\Integrations\Http\Controllers\ModirPayamakAdminController;
use Modules\Integrations\Http\Controllers\ModirPayamakCustomerController;
use Modules\Integrations\Http\Controllers\ModirPayamakSettingsController;
use Modules\Integrations\Http\Controllers\PaymentCallbackController;
use Modules\Integrations\Http\Controllers\PaymentCheckoutController;
use Modules\Integrations\Http\Controllers\PaymentGatewaySettingsController;
use Modules\Integrations\Http\Controllers\PaymentIntegrationController;
use Modules\Integrations\Http\Controllers\SmsIntegrationController;
use Modules\Integrations\Http\Controllers\LiveConnectController;
use Modules\Integrations\Http\Controllers\TelegramIntegrationController;

Route::post('/calendars/webhook/google', [LiveConnectController::class, 'googleWebhook'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/calendars/webhook/outlook', [LiveConnectController::class, 'outlookWebhook'])->middleware('throttle:120,1');
Route::get('/calendars/callback/{provider}', [LiveConnectController::class, 'callback'])->whereIn('provider', ['google', 'outlook']);
Route::post('/bridges/webhook/{provider}', [LiveConnectController::class, 'bridgeWebhook'])->whereIn('provider', ['slack', 'bale', 'telegram'])->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations'])->group(function () {
    Route::get('/calendars/connect/{provider}', [LiveConnectController::class, 'connectUrl'])->whereIn('provider', ['google', 'outlook']);
    Route::get('/calendars', [LiveConnectController::class, 'calendarsIndex']);
    Route::post('/calendars', [LiveConnectController::class, 'storeCalendar']);
    Route::post('/calendars/{id}/sync', [LiveConnectController::class, 'syncCalendar'])->whereNumber('id');
    Route::get('/bridges', [LiveConnectController::class, 'bridgesIndex']);
    Route::post('/bridges', [LiveConnectController::class, 'storeBridge']);
    Route::get('/bridges/{id}/messages', [LiveConnectController::class, 'bridgeMessages'])->whereNumber('id');
    Route::get('/mailbox/search', [LiveConnectController::class, 'searchMail']);
    Route::get('/mailbox', [LiveConnectController::class, 'mailboxIndex']);
    Route::post('/mailbox', [LiveConnectController::class, 'storeMailbox']);
    Route::post('/mailbox/{id}/sync', [LiveConnectController::class, 'syncMailbox'])->whereNumber('id');
    Route::post('/mailbox/{id}/import', [LiveConnectController::class, 'importMail'])->whereNumber('id');
    Route::get('/mailbox/{id}/threads', [LiveConnectController::class, 'threads'])->whereNumber('id');
    Route::post('/mailbox/{id}/send', [LiveConnectController::class, 'sendMail'])->whereNumber('id');
    Route::post('/mailbox/messages/{id}/link', [LiveConnectController::class, 'linkMail'])->whereNumber('id');
    Route::get('/notifications/feed', [LiveConnectController::class, 'feed']);
    Route::post('/notifications/dispatch', [LiveConnectController::class, 'dispatchNote']);
    Route::get('/sms-rules', [LiveConnectController::class, 'smsPolicy']);
    Route::put('/sms-rules', [LiveConnectController::class, 'saveSmsPolicy']);
});

Route::get('/sms/settings', [SmsIntegrationController::class, 'getSettings'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/sms/send', [SmsIntegrationController::class, 'send'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::put('/sms/settings', [SmsIntegrationController::class, 'updateSettings'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);

Route::post('/payments/initiate', [PaymentIntegrationController::class, 'initiate'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/payments/verify', [PaymentIntegrationController::class, 'verify']);
Route::match(['get', 'post'], '/payments/callback/{gateway}', [PaymentCallbackController::class, 'handle'])
    ->whereIn('gateway', ['zarinpal', 'snappay', 'digipay', 'torobpay'])
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations'])->group(function () {
    Route::get('/payments/gateways', [PaymentGatewaySettingsController::class, 'index']);
    Route::put('/payments/gateways', [PaymentGatewaySettingsController::class, 'update']);
    Route::post('/payments/gateways/{code}/test', [PaymentGatewaySettingsController::class, 'test'])
        ->whereIn('code', ['zarinpal', 'snappay', 'digipay', 'torobpay']);
    Route::get('/payments/options', [PaymentCheckoutController::class, 'options']);
    Route::post('/payments/quote', [PaymentCheckoutController::class, 'quote']);
    Route::get('/payments/bills', [PaymentCheckoutController::class, 'bills']);
    Route::get('/payments/intents', [PaymentCheckoutController::class, 'index']);
    Route::post('/payments/intents', [PaymentCheckoutController::class, 'store']);
    Route::get('/payments/intents/{publicId}', [PaymentCheckoutController::class, 'show']);
    Route::post('/payments/intents/{publicId}/cancel', [PaymentCheckoutController::class, 'cancel']);
});

Route::post('/bale/messages', [BaleIntegrationController::class, 'sendMessage'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/bale/messages/bulk', [BaleIntegrationController::class, 'sendBulkMessage'])->middleware(['auth:sanctum', 'module:integrations', 'module.permission:integrations']);
Route::post('/bale/webhook', [BaleIntegrationController::class, 'webhook'])->middleware('throttle:60,1');

Route::post('/telegram/webhook', [TelegramIntegrationController::class, 'webhook'])->middleware('throttle:60,1');
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
        Route::get('/customers/ledger', [ModirPayamakAdminController::class, 'customerLedger']);
        Route::get('/packages', [ModirPayamakAdminController::class, 'packagesIndex']);
        Route::post('/packages', [ModirPayamakAdminController::class, 'packagesStore']);
        Route::delete('/packages/{package}', [ModirPayamakAdminController::class, 'packagesDestroy']);
        Route::get('/tariffs', [ModirPayamakAdminController::class, 'tariffsIndex']);
        Route::post('/tariffs', [ModirPayamakAdminController::class, 'tariffsStore']);
        Route::delete('/tariffs/{id}', [ModirPayamakAdminController::class, 'tariffsDestroy'])->whereNumber('id');
        Route::get('/secretaries', [ModirPayamakAdminController::class, 'secretariesIndex']);
        Route::post('/secretaries', [ModirPayamakAdminController::class, 'secretariesStore']);
        Route::post('/secretaries/delete', [ModirPayamakAdminController::class, 'secretariesDestroy']);
        Route::delete('/secretaries', [ModirPayamakAdminController::class, 'secretariesDestroy']);
        Route::get('/orders', [ModirPayamakAdminController::class, 'orders']);
        Route::post('/send', [ModirPayamakAdminController::class, 'adminSend']);
        Route::get('/messages', [ModirPayamakAdminController::class, 'messages']);
        Route::get('/reports/outbox', [ModirPayamakAdminController::class, 'reportsOutbox']);
        Route::get('/reports/inbox', [ModirPayamakAdminController::class, 'reportsInbox']);
        Route::get('/reports/outbox/{id}', [ModirPayamakAdminController::class, 'reportOutboxDetail']);
        Route::get('/patterns', [ModirPayamakAdminController::class, 'patterns']);
        Route::post('/patterns/attach', [ModirPayamakAdminController::class, 'attachPattern']);
        Route::post('/patterns/detach', [ModirPayamakAdminController::class, 'detachPattern']);
        Route::get('/patterns/registry', [ModirPayamakAdminController::class, 'patternRegistry']);
        Route::get('/numbers', [ModirPayamakAdminController::class, 'numbers']);
        Route::post('/numbers/attach', [ModirPayamakAdminController::class, 'attachNumber']);
        Route::post('/numbers/detach', [ModirPayamakAdminController::class, 'detachNumber']);
        Route::match(['get', 'post'], '/phonebooks', [ModirPayamakAdminController::class, 'phonebooks']);
        Route::match(['get', 'post'], '/phonebooks/{id}/contacts', [ModirPayamakAdminController::class, 'phonebookContacts'])->whereNumber('id');
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
