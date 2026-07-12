<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your module. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "web" middleware group.
|
*/

Route::get('/', function () {
    return view('core::welcome');
});

