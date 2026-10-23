<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Hub\HubContractController as C;
use Modules\Crm\Http\Controllers\Hub\HubDocumentController as D;
use Modules\Crm\Http\Controllers\Hub\HubProjectController as P;

// Customer 360 phase 1 list endpoints. Included at the TOP of hub.php so they win over the
// generic GET /{id}/hub/{section} catch-all (first match wins for distinct URIs).
Route::get('/{id}/hub/documents', [D::class, 'index']);
Route::get('/{id}/hub/contracts', [C::class, 'index']);
Route::get('/{id}/hub/projects', [P::class, 'index']);
