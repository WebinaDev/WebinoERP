<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['message' => 'Webina Enterprise CRM API'];
});


Route::middleware('throttle:60,1')->group(function () {
    Route::get('/renew/{license}', [\Modules\Core\Http\Controllers\LicenseRenewalPublicController::class, 'page'])
        ->whereNumber('license')->name('license-renewal.page');
    Route::get('/renew/{license}/pay/{option}', [\Modules\Core\Http\Controllers\LicenseRenewalPublicController::class, 'pay'])
        ->whereNumber('license')->whereNumber('option')->name('license-renewal.pay');
});
