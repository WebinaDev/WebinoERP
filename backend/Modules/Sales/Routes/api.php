<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\CampaignController;
use Modules\Sales\Http\Controllers\CatalogController;
use Modules\Sales\Http\Controllers\InvoiceController;
use Modules\Sales\Http\Controllers\RahnController;
use Modules\Sales\Http\Controllers\RahnPublicController;
use Modules\Sales\Http\Controllers\ServicesController;

Route::apiResource('catalog', CatalogController::class);
Route::apiResource('campaigns', CampaignController::class);
Route::apiResource('invoices', InvoiceController::class);
Route::get('invoices-meta/projects', [InvoiceController::class, 'projectsForCustomer']);
Route::post('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email']);

Route::prefix('services')->group(function () {
    Route::get('subscriptions', [ServicesController::class, 'subscriptions']);
    Route::get('products', [ServicesController::class, 'products']);
    Route::get('task-templates', [ServicesController::class, 'taskTemplates']);
    Route::post('subscriptions/{catalog}/convert-contract', [ServicesController::class, 'convertContract']);
    Route::post('products/{catalog}/convert-contract', [ServicesController::class, 'convertContract']);
    Route::patch('products/{catalog}/task-template', [ServicesController::class, 'updateProductTaskTemplate']);
});

Route::prefix('rahn')->group(function () {
    Route::get('settings', [RahnController::class, 'getSettings']);
    Route::put('settings', [RahnController::class, 'updateSettings']);
    Route::post('settings', [RahnController::class, 'updateSettings']);
    Route::post('calculate', [RahnController::class, 'calculate']);

    Route::get('quotes', [RahnController::class, 'quotesIndex']);
    Route::post('quotes', [RahnController::class, 'quotesStore']);
    Route::post('quotes/{id}/lock', [RahnController::class, 'quotesLock'])->whereNumber('id');
    Route::post('quotes/{id}/contract', [RahnController::class, 'quotesContract'])->whereNumber('id');
    Route::delete('quotes/{id}', [RahnController::class, 'quotesDestroy'])->whereNumber('id');

    Route::get('contracts', [RahnController::class, 'contractsIndex']);
    Route::get('customers', [RahnController::class, 'customersIndex']);

    Route::post('statements/calculate', [RahnController::class, 'statementsCalculate']);
    Route::post('statements', [RahnController::class, 'statementsStore']);
    Route::get('statements', [RahnController::class, 'statementsIndex']);
});

// Public share — no sanctum (Marketplace payment-callback / basalam pattern).
Route::prefix('rahn/public')->middleware('throttle:60,1')->group(function () {
    Route::get('{token}', [RahnPublicController::class, 'show'])
        ->where('token', '[a-zA-Z0-9]+')
        ->withoutMiddleware(['auth:sanctum', 'module:sales', 'module.permission:sales']);
    Route::post('{token}/calculate', [RahnPublicController::class, 'calculate'])
        ->where('token', '[a-zA-Z0-9]+')
        ->withoutMiddleware(['auth:sanctum', 'module:sales', 'module.permission:sales']);
    Route::post('{token}/submit', [RahnPublicController::class, 'submit'])
        ->where('token', '[a-zA-Z0-9]+')
        ->withoutMiddleware(['auth:sanctum', 'module:sales', 'module.permission:sales']);
});
