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


// Order-to-Cash & Growth Attribution
Route::prefix('quotes')->group(function () {
    Route::get('/', [\Modules\Sales\Http\Controllers\QuoteController::class, 'index']);
    Route::post('/', [\Modules\Sales\Http\Controllers\QuoteController::class, 'store']);
    Route::post('/{quote}/convert', [\Modules\Sales\Http\Controllers\QuoteController::class, 'convert']);
    Route::get('/growth/attribution', [\Modules\Sales\Http\Controllers\QuoteController::class, 'growthAttribution']);
});

// Point of sale (registers, shifts, checkout, refunds, Z reports). Flat 'pos/...' URIs so module.permission maps the 'pos' segment.
$posC = \Modules\Sales\Http\Controllers\PosController::class;
Route::get('pos/overview', [$posC, 'overview']);
Route::get('pos/registers', [$posC, 'registers']);
Route::post('pos/registers', [$posC, 'storeRegister']);
Route::put('pos/registers/{id}', [$posC, 'updateRegister'])->whereNumber('id');
Route::get('pos/warehouses', [$posC, 'warehouses']);
Route::get('pos/sessions', [$posC, 'sessions']);
Route::get('pos/sessions/current', [$posC, 'current']);
Route::post('pos/sessions', [$posC, 'openSession']);
Route::get('pos/sessions/{id}', [$posC, 'showSession'])->whereNumber('id');
Route::post('pos/sessions/{id}/close', [$posC, 'closeSession'])->whereNumber('id');
Route::post('pos/sessions/{id}/cash-movements', [$posC, 'cashMovement'])->whereNumber('id');
Route::get('pos/products', [$posC, 'products']);
Route::get('pos/products/{id}/variants', [$posC, 'variants'])->whereNumber('id');
Route::post('pos/products/{id}/variants', [$posC, 'storeVariant'])->whereNumber('id');
Route::put('pos/variants/{variantId}', [$posC, 'updateVariant'])->whereNumber('variantId');
Route::get('pos/customers', [$posC, 'customers']);
Route::post('pos/customers', [$posC, 'storeCustomer']);
Route::get('pos/orders', [$posC, 'orders']);
Route::post('pos/orders', [$posC, 'checkout']);
Route::get('pos/orders/{id}', [$posC, 'showOrder'])->whereNumber('id');
Route::post('pos/orders/{id}/refund', [$posC, 'refund'])->whereNumber('id');
Route::get('pos/reports/daily', [$posC, 'dailyReport']);
