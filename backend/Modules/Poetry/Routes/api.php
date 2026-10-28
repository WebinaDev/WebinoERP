<?php

use Illuminate\Support\Facades\Route;
use Modules\Poetry\Http\Controllers\PoetryAdminController as C;

Route::get('/poems', [C::class, 'index']);
Route::post('/poems', [C::class, 'store']);
Route::get('/poems/{poem}', [C::class, 'show'])->whereNumber('poem');
Route::put('/poems/{poem}', [C::class, 'update'])->whereNumber('poem');
Route::delete('/poems/{poem}', [C::class, 'destroy'])->whereNumber('poem');
Route::post('/import/parse', [C::class, 'parse']);
Route::get('/terms', [C::class, 'terms']);
Route::post('/terms', [C::class, 'storeTerm']);
Route::put('/terms/{term}', [C::class, 'updateTerm'])->whereNumber('term');
Route::delete('/terms/{term}', [C::class, 'destroyTerm'])->whereNumber('term');
Route::get('/media', [\Modules\Poetry\Http\Controllers\PoetryMediaAdminController::class, 'index']);
Route::post('/media', [\Modules\Poetry\Http\Controllers\PoetryMediaAdminController::class, 'store']);
Route::put('/media/{media}', [\Modules\Poetry\Http\Controllers\PoetryMediaAdminController::class, 'update'])->whereNumber('media');
Route::delete('/media/{media}', [\Modules\Poetry\Http\Controllers\PoetryMediaAdminController::class, 'destroy'])->whereNumber('media');
