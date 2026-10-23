<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Customer360\HubAccountingController as Acc;
use Modules\Crm\Http\Controllers\Customer360\HubOrdersController as Ord;
use Modules\Crm\Http\Controllers\Customer360\HubSmsController as Sms;

// Customer 360 phase 3 (PHASE3_API.md) — required from hub.php before the {section} catch-all.
Route::get('/{id}/hub/orders', [Ord::class, 'index']);
Route::get('/{id}/hub/orders/{ref}', [Ord::class, 'show'])->where('ref', '(mo|bs)-[0-9]+');
Route::get('/{id}/hub/basalam/stores', [Ord::class, 'stores']);
Route::post('/{id}/hub/basalam/stores', [Ord::class, 'link']);
Route::delete('/{id}/hub/basalam/stores/{connectionId}', [Ord::class, 'unlink'])->whereNumber('connectionId');
Route::get('/{id}/hub/accounting', [Acc::class, 'summary']);
Route::get('/{id}/hub/accounting/ledger', [Acc::class, 'ledger']);
Route::get('/{id}/hub/accounting/invoices', [Acc::class, 'invoices']);
Route::get('/{id}/hub/accounting/payments', [Acc::class, 'payments']);
Route::get('/{id}/hub/sms', [Sms::class, 'index']);
Route::post('/{id}/hub/sms', [Sms::class, 'send']);
