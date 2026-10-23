<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Customer360\CustomerAutomationsController as Auto;

// Customer 360 collection-level routes (prefix api/v1/crm/customers), registered before Routes/api.php.
Route::get('/automations', [Auto::class, 'index']);
Route::patch('/automations/{key}', [Auto::class, 'update'])->where('key', '[a-z_]+');
