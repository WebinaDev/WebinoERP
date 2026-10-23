<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CustomerTimelineController;

// Customer 360 phase 2 — unified timeline (crm_customer_activities). Must precede the {section} catch-all.
Route::get('/{id}/hub/timeline', [CustomerTimelineController::class, 'index']);
