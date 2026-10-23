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
