<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Hub\HubContractController as C;
use Modules\Crm\Http\Controllers\Hub\HubDocumentController as D;
use Modules\Crm\Http\Controllers\Hub\HubProjectController as P;

// Customer 360 phase 1 (contract §5). Included at the END of hub.php: for identical method+URI
// Laravel keeps the last registration, so these replace the legacy document write routes
// (POST/PATCH/DELETE documents) with the versioned implementation. download-link stays in CustomerHubController.

// Documents
Route::get('/{id}/hub/documents/{docId}', [D::class, 'show'])->whereNumber('docId');
Route::post('/{id}/hub/documents', [D::class, 'store']);
Route::patch('/{id}/hub/documents/{docId}', [D::class, 'update'])->whereNumber('docId');
Route::post('/{id}/hub/documents/{docId}/versions', [D::class, 'storeVersion'])->whereNumber('docId');
Route::delete('/{id}/hub/documents/{docId}', [D::class, 'destroy'])->whereNumber('docId');

// Contracts
Route::whereNumber('contractId')->group(function () {
    Route::get('/{id}/hub/contracts/{contractId}', [C::class, 'show']);
    Route::post('/{id}/hub/contracts', [C::class, 'store']);
    Route::patch('/{id}/hub/contracts/{contractId}', [C::class, 'update']);
    Route::post('/{id}/hub/contracts/{contractId}/sign', [C::class, 'sign']);
    Route::post('/{id}/hub/contracts/{contractId}/cancel', [C::class, 'cancel']);
    Route::post('/{id}/hub/contracts/{contractId}/renew', [C::class, 'renew']);
    Route::post('/{id}/hub/contracts/{contractId}/installments/{instId}/invoice', [C::class, 'invoiceInstallment'])->whereNumber('instId');
    Route::post('/{id}/hub/contracts/{contractId}/projects', [C::class, 'attachProject']);
    Route::delete('/{id}/hub/contracts/{contractId}', [C::class, 'destroy']);
});

// Projects
Route::whereNumber('projectId')->group(function () {
    Route::get('/{id}/hub/projects/{projectId}', [P::class, 'show']);
    Route::post('/{id}/hub/projects', [P::class, 'store']);
    Route::patch('/{id}/hub/projects/{projectId}', [P::class, 'update']);
    Route::post('/{id}/hub/projects/{projectId}/tasks', [P::class, 'storeTask']);
});
