<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\WebinocrmModirPayamakCompatController;

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/modirpayamak/{path?}', [WebinocrmModirPayamakCompatController::class, 'handle'])
    ->where('path', '.*');
