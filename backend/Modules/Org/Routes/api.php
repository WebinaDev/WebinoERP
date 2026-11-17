<?php

use Illuminate\Support\Facades\Route;
use Modules\Org\Http\Controllers\OrgController;

Route::get('/companies', [OrgController::class, 'companies']);
Route::post('/companies', [OrgController::class, 'storeCompany']);

Route::get('/branches', [OrgController::class, 'branches']);
Route::post('/branches', [OrgController::class, 'storeBranch']);

Route::get('/context/active', [OrgController::class, 'activeContext']);
Route::post('/context/switch', [OrgController::class, 'switchContext']);

Route::get('/sequences', [OrgController::class, 'sequences']);
Route::post('/sequences', [OrgController::class, 'storeSequence']);
Route::post('/sequences/next', [OrgController::class, 'nextNumber']);

Route::get('/transfers', [OrgController::class, 'transfers']);
Route::post('/transfers', [OrgController::class, 'storeTransfer']);
Route::post('/transfers/{id}/receive', [OrgController::class, 'receiveTransfer'])->whereNumber('id');

Route::get('/reports/consolidated', [OrgController::class, 'consolidatedReport']);
