<?php

use Illuminate\Support\Facades\Route;
use Modules\DeviceGateway\Http\Controllers\DeviceGatewayController;

Route::get('/gateways', [DeviceGatewayController::class, 'gateways']);
Route::post('/gateways', [DeviceGatewayController::class, 'storeGateway']);
Route::post('/gateways/{gatewayKey}/heartbeat', [DeviceGatewayController::class, 'heartbeat']);

Route::get('/peripherals', [DeviceGatewayController::class, 'peripherals']);
Route::post('/peripherals', [DeviceGatewayController::class, 'storePeripheral']);

Route::get('/print-jobs', [DeviceGatewayController::class, 'printJobs']);
Route::post('/print-jobs/dispatch', [DeviceGatewayController::class, 'dispatchPrint']);
