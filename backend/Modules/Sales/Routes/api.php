<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\CampaignController;
use Modules\Sales\Http\Controllers\CatalogController;
use Modules\Sales\Http\Controllers\InvoiceController;
use Modules\Sales\Http\Controllers\ServicesController;

Route::apiResource('catalog', CatalogController::class);
Route::apiResource('campaigns', CampaignController::class);
Route::apiResource('invoices', InvoiceController::class);
Route::post('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email']);

Route::prefix('services')->group(function () {
    Route::get('subscriptions', [ServicesController::class, 'subscriptions']);
    Route::get('products', [ServicesController::class, 'products']);
    Route::get('task-templates', [ServicesController::class, 'taskTemplates']);
    Route::post('subscriptions/{catalog}/convert-contract', [ServicesController::class, 'convertContract']);
});
