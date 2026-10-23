<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CustomerProfileController as P;

// Customer 360 phase 1 (edit form). Included from hub.php *before* the generic /hub/{section} route.
Route::get('/{id}/hub/profile', [P::class, 'show']);
Route::patch('/{id}/hub/profile', [P::class, 'update']);
Route::get('/{id}/hub/addresses', [P::class, 'addresses']);
Route::post('/{id}/hub/addresses', [P::class, 'storeAddress']);
Route::patch('/{id}/hub/addresses/{addressId}', [P::class, 'updateAddress'])->whereNumber('addressId');
Route::delete('/{id}/hub/addresses/{addressId}', [P::class, 'deleteAddress'])->whereNumber('addressId');
Route::post('/{id}/hub/logo', [P::class, 'uploadLogo']);
Route::post('/{id}/hub/merge', [P::class, 'merge']);
