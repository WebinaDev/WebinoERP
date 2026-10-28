<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Customer360\CustomerAutomationsController as Auto;
use Modules\Crm\Http\Controllers\Customer360\CustomerDuplicatesController as Dup;
use Modules\Crm\Http\Controllers\Customer360\CustomerImportExportController as IO;
use Modules\Crm\Http\Controllers\Customer360\CustomerReportsController as Bi;

// Customer 360 collection-level routes (prefix api/v1/crm/customers), registered before the {id} routes.
Route::get('/automations', [Auto::class, 'index']);
Route::patch('/automations/{key}', [Auto::class, 'update'])->where('key', '[a-z_]+');

Route::get('/duplicates', [Dup::class, 'index']);
Route::post('/duplicates/dismiss', [Dup::class, 'dismiss']);
Route::get('/{id}/hub/duplicates', [Dup::class, 'forAccount'])->whereNumber('id');

Route::get('/export', [IO::class, 'export']);
Route::get('/import/template', [IO::class, 'template']);
Route::post('/import', [IO::class, 'import'])->middleware('throttle:10,1');

Route::get('/reports/{section}', [Bi::class, 'show'])->where('section', '[a-z]+');
