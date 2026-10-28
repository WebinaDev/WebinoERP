<?php

use Illuminate\Support\Facades\Route;
use Modules\Poetry\Http\Controllers\PublicPoetryController as C;

Route::get('/terms', [C::class, 'terms']);
Route::get('/poems', [C::class, 'index']);
Route::get('/popular', [C::class, 'popular']);
Route::get('/poems/{slug}', [C::class, 'show']);
Route::get('/poems/{slug}/text', [C::class, 'text']);
Route::get('/figures/{slug}', [C::class, 'figure']);
Route::get('/media', [C::class, 'media']);
