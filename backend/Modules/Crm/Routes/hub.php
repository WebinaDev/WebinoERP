<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CustomerHubController as H;

// Prefix: api/v1/crm/customers — middleware: api, auth:sanctum, module:crm (RBAC per section in controller)
Route::whereNumber('id')->group(function () {
    require __DIR__.'/hub_tabs_index.php'; // Customer 360 phase 1 lists (must precede the {section} catch-all)
    require __DIR__.'/hub_profile.php';
    require __DIR__.'/hub_timeline.php';
    Route::get('/{id}/hub', [H::class, 'hub']);
    Route::get('/{id}/hub/export.csv', [H::class, 'exportCsv']);
    Route::get('/{id}/hub/{section}', [H::class, 'section'])->where('section', '[a-z_]+');

    Route::post('/{id}/hub/contacts', [H::class, 'saveContact']);
    Route::patch('/{id}/hub/contacts/{contactId}', [H::class, 'saveContact'])->whereNumber('contactId');
    Route::delete('/{id}/hub/contacts/{contactId}', [H::class, 'deleteContact'])->whereNumber('contactId');
    Route::post('/{id}/hub/documents', [H::class, 'storeDocument']);
    Route::post('/{id}/hub/documents/{docId}/download-link', [H::class, 'documentLink'])->whereNumber('docId');
    Route::patch('/{id}/hub/documents/{docId}', [H::class, 'updateDocument'])->whereNumber('docId');
    Route::delete('/{id}/hub/documents/{docId}', [H::class, 'deleteDocument'])->whereNumber('docId');
    Route::post('/{id}/hub/domains', [H::class, 'storeDomain']);
    Route::delete('/{id}/hub/domains/{domainId}', [H::class, 'deleteDomain'])->whereNumber('domainId');
    Route::post('/{id}/hub/services', [H::class, 'storeService']);
    Route::delete('/{id}/hub/services/{serviceId}', [H::class, 'deleteService'])->whereNumber('serviceId');
    Route::post('/{id}/hub/relations', [H::class, 'storeRelation']);
    Route::delete('/{id}/hub/relations/{relId}', [H::class, 'deleteRelation'])->whereNumber('relId');
    Route::post('/{id}/hub/alerts', [H::class, 'storeAlert']);
    Route::post('/{id}/hub/alerts/{alertId}/resolve', [H::class, 'resolveAlert'])->whereNumber('alertId');
    Route::post('/{id}/hub/onboarding', [H::class, 'storeOnboarding']);
    Route::post('/{id}/hub/onboarding/{itemId}/toggle', [H::class, 'toggleOnboarding'])->whereNumber('itemId');
    Route::post('/{id}/hub/usage-metrics/sync', [H::class, 'syncUsage']);
    Route::post('/{id}/hub/usage-metrics', [H::class, 'storeUsageMetric']);

    // Action bar
    Route::post('/{id}/hub/actions/note', [H::class, 'addNote']);
    Route::post('/{id}/hub/actions/invoice', [H::class, 'createInvoice']);
    Route::post('/{id}/hub/actions/payment', [H::class, 'recordPayment']);
    Route::post('/{id}/hub/actions/license', [H::class, 'issueLicense']);
    Route::post('/{id}/hub/actions/license/{licenseId}/renew', [H::class, 'renewLicense'])->whereNumber('licenseId');
    Route::post('/{id}/hub/actions/ticket', [H::class, 'createTicket']);
    Route::post('/{id}/hub/actions/project', [H::class, 'createProject']);
    Route::post('/{id}/hub/actions/message', [H::class, 'sendMessage']);
    Route::post('/{id}/hub/actions/owner', [H::class, 'changeOwner']);
    Route::post('/{id}/hub/actions/suspend', [H::class, 'suspend']);
    Route::post('/{id}/hub/actions/activate', [H::class, 'activate']);
    require __DIR__.'/hub_tabs.php'; // Customer 360 phase 1 writes (must stay last)
});
