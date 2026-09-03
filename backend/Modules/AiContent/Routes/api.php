<?php

use Illuminate\Support\Facades\Route;
use Modules\AiContent\Http\Controllers\CalendarController;
use Modules\AiContent\Http\Controllers\JobController;
use Modules\AiContent\Http\Controllers\OverviewController;
use Modules\AiContent\Http\Controllers\PageController;
use Modules\AiContent\Http\Controllers\ProductController;
use Modules\AiContent\Http\Controllers\ProposalController;
use Modules\AiContent\Http\Controllers\SettingsController;
use Modules\AiContent\Http\Controllers\TaxonomyController;

Route::get('overview', OverviewController::class);

Route::get('settings', [SettingsController::class, 'show']);
Route::post('settings', [SettingsController::class, 'store']);
Route::get('cost-estimate', [SettingsController::class, 'costEstimate']);
Route::post('cost-estimate', [SettingsController::class, 'costEstimate']);
Route::get('gapgpt/models', [SettingsController::class, 'gapGptModels']);
Route::get('queue', [SettingsController::class, 'queueShow']);
Route::post('queue', [SettingsController::class, 'queueStore']);

Route::get('design-memory', [SettingsController::class, 'designMemoryShow']);
Route::post('design-memory', [SettingsController::class, 'designMemoryStore']);
Route::post('design-memory/extract', [SettingsController::class, 'designMemoryExtract']);
Route::post('design-memory/reset', [SettingsController::class, 'designMemoryReset']);

Route::get('jobs', [JobController::class, 'index']);
Route::post('jobs/run-due', [JobController::class, 'runDue']);
Route::post('jobs/cancel-pending', [JobController::class, 'cancelPending']);
Route::get('jobs/{id}', [JobController::class, 'show'])->whereNumber('id');
Route::post('jobs/{id}/run', [JobController::class, 'run'])->whereNumber('id');
Route::post('jobs/{id}/retry', [JobController::class, 'retry'])->whereNumber('id');
Route::post('jobs/{id}/cancel', [JobController::class, 'cancel'])->whereNumber('id');
Route::post('generate', [JobController::class, 'generate']);

Route::get('calendar', [CalendarController::class, 'index']);
Route::post('calendar', [CalendarController::class, 'store']);
Route::post('calendar/bulk', [CalendarController::class, 'bulk']);
Route::delete('calendar/{id}', [CalendarController::class, 'destroy'])->whereNumber('id');
Route::post('calendar/run-due', [CalendarController::class, 'runDue']);

Route::get('products', [ProductController::class, 'index']);
Route::post('products', [ProductController::class, 'store']);
Route::patch('products/{id}', [ProductController::class, 'update'])->whereNumber('id');
Route::delete('products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');
Route::get('products/incomplete', [ProductController::class, 'incomplete']);
Route::post('products/fill-batch', [ProductController::class, 'fillBatch']);

Route::get('pages', [PageController::class, 'index']);
Route::post('pages', [PageController::class, 'store']);
Route::patch('pages/{id}', [PageController::class, 'update'])->whereNumber('id');
Route::delete('pages/{id}', [PageController::class, 'destroy'])->whereNumber('id');

Route::get('proposals/{kind}', [ProposalController::class, 'index'])->where('kind', 'title|catalog');
Route::post('proposals/{kind}/enqueue', [ProposalController::class, 'enqueue'])->where('kind', 'title|catalog');
Route::post('proposals/{id}/apply', [ProposalController::class, 'apply'])->whereNumber('id');
Route::post('proposals/{id}/skip', [ProposalController::class, 'skip'])->whereNumber('id');
Route::post('proposals/{kind}/product/{product_id}/requeue', [ProposalController::class, 'requeue'])
    ->where('kind', 'title|catalog')
    ->whereNumber('product_id');

Route::post('suggest-categories', [TaxonomyController::class, 'suggestCategories']);
Route::get('suggest-categories/{kind}', [TaxonomyController::class, 'getCategorySuggestions'])->where('kind', 'blog|product');
Route::post('suggest-categories/{kind}/apply', [TaxonomyController::class, 'applyCategorySuggestions'])->where('kind', 'blog|product');
Route::post('terms/fill-batch', [TaxonomyController::class, 'fillTermsBatch']);

Route::get('attribute-templates', [TaxonomyController::class, 'attrTemplates']);
Route::post('attribute-templates', [TaxonomyController::class, 'saveAttrTemplate']);
Route::post('attribute-templates/{cat_id}/suggest', [TaxonomyController::class, 'suggestAttrTemplate'])->whereNumber('cat_id');
Route::get('attribute-templates/{cat_id}/draft', [TaxonomyController::class, 'attrDraft'])->whereNumber('cat_id');
Route::delete('attribute-templates/{cat_id}', [TaxonomyController::class, 'deleteAttrTemplate'])->whereNumber('cat_id');
