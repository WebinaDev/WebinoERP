<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\AccountController;
use Modules\Crm\Http\Controllers\ActivityController;
use Modules\Crm\Http\Controllers\ConsultationController;
use Modules\Crm\Http\Controllers\ContactController;
use Modules\Crm\Http\Controllers\CrmParityController;
use Modules\Crm\Http\Controllers\DealController;
use Modules\Crm\Http\Controllers\LeadAdvancedController;
use Modules\Crm\Http\Controllers\LeadController;
use Modules\Crm\Http\Controllers\PipelineController;
use Modules\Crm\Http\Controllers\SourceController;

/*
|--------------------------------------------------------------------------
| API — /api/v1/crm
|--------------------------------------------------------------------------
*/

Route::get('/leads/export', [CrmParityController::class, 'exportLeads']);
Route::post('/leads/import', [CrmParityController::class, 'importLeads']);
Route::get('/leads/assignees', [CrmParityController::class, 'leadAssignees']);

Route::get('/leads', [LeadController::class, 'index'])->middleware('fieldsec:lead');
Route::post('/leads', [LeadController::class, 'store'])->middleware('fieldsec:lead');
Route::get('/leads/{lead}', [LeadController::class, 'show'])->middleware('fieldsec:lead');
Route::patch('/leads/{lead}', [LeadController::class, 'update'])->middleware('fieldsec:lead');
Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->middleware('fieldsec:lead');
Route::patch('/leads/{id}/status', [CrmParityController::class, 'changeLeadStatus'])->whereNumber('id');
Route::patch('/leads/{id}/assign', [CrmParityController::class, 'assignLead'])->whereNumber('id');
Route::get('/leads/{id}/for-contract', [CrmParityController::class, 'leadForContract'])->whereNumber('id');
Route::post('/leads/{id}/convert', [LeadAdvancedController::class, 'convert'])->whereNumber('id');
Route::get('/leads/{lead}/duplicates', [LeadAdvancedController::class, 'duplicates']);
Route::post('/leads/{lead}/score', [LeadAdvancedController::class, 'score']);
Route::post('/leads/merge', [LeadAdvancedController::class, 'merge']);
Route::post('/leads/bulk-assign', [LeadAdvancedController::class, 'bulkAssign']);
Route::post('/leads/bulk-delete', [LeadAdvancedController::class, 'bulkDelete']);
Route::post('/leads/recompute-scores', [LeadAdvancedController::class, 'recomputeScores']);

Route::get('/statuses', [CrmParityController::class, 'leadStatuses']);
Route::post('/statuses', [CrmParityController::class, 'storeLeadStatus']);
Route::delete('/statuses/{id}', [CrmParityController::class, 'deleteLeadStatus'])->whereNumber('id');

Route::get('/accounts/summary', [CrmParityController::class, 'accountsSummary'])->middleware('fieldsec:account');
Route::get('/accounts', [AccountController::class, 'index'])->middleware('fieldsec:account');
Route::post('/accounts', [AccountController::class, 'store'])->middleware('fieldsec:account');
Route::get('/accounts/{id}', [AccountController::class, 'show'])->middleware('fieldsec:account')->whereNumber('id');
Route::patch('/accounts/{id}', [AccountController::class, 'update'])->middleware('fieldsec:account')->whereNumber('id');
Route::delete('/accounts/{id}', [AccountController::class, 'destroy'])->middleware('fieldsec:account')->whereNumber('id');
Route::post('/accounts/bulk-delete', [AccountController::class, 'bulkDelete'])->middleware('fieldsec:account');
Route::get('/accounts/list', [CrmParityController::class, 'accountsList']);
Route::get('/accounts/export', [CrmParityController::class, 'exportAccounts']);
Route::post('/accounts/import', [CrmParityController::class, 'importAccounts']);
Route::get('/accounts/{id}/360', [CrmParityController::class, 'account360'])->middleware('fieldsec:account')->whereNumber('id');

Route::get('/consultations', [ConsultationController::class, 'index']);
Route::post('/consultations', [ConsultationController::class, 'store']);
Route::patch('/consultations/{id}', [ConsultationController::class, 'update'])->whereNumber('id');
Route::put('/consultations/{id}', [ConsultationController::class, 'update'])->whereNumber('id');
Route::post('/consultations/{id}/convert-project', [CrmParityController::class, 'convertConsultation'])->whereNumber('id');

Route::apiResource('deals', DealController::class);
Route::patch('deals/{deal}/move', [DealController::class, 'move']);
Route::apiResource('contacts', ContactController::class);
Route::apiResource('pipelines', PipelineController::class);
Route::get('pipelines/{pipeline}/kanban', [PipelineController::class, 'kanban']);
Route::get('sources', [SourceController::class, 'index']);
Route::post('sources', [SourceController::class, 'store']);
Route::get('activities', [ActivityController::class, 'index']);
Route::post('activities', [ActivityController::class, 'store']);
Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);
Route::post('pipelines/{pipeline}/stages', [PipelineController::class, 'storeStage']);
Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'updateStage']);
Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'destroyStage']);
