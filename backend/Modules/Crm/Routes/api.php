<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\AccountController;
use Modules\Crm\Http\Controllers\ActivityController;
use Modules\Crm\Http\Controllers\ConsultationController;
use Modules\Crm\Http\Controllers\ConsultationStatusController;
use Modules\Crm\Http\Controllers\ContactController;
use Modules\Crm\Http\Controllers\CrmParityController;
use Modules\Crm\Http\Controllers\DealController;
use Modules\Crm\Http\Controllers\AudienceController;
use Modules\Crm\Http\Controllers\ForecastController;
use Modules\Crm\Http\Controllers\LeadAdvancedController;
use Modules\Crm\Http\Controllers\LeadController;
use Modules\Crm\Http\Controllers\OutreachController;
use Modules\Crm\Http\Controllers\PipelineController;
use Modules\Crm\Http\Controllers\CrmExpansionController;
use Modules\Crm\Http\Controllers\CrmProductionController;
use Modules\Crm\Http\Controllers\SourceController;
use Modules\Crm\Http\Controllers\TimelineController;

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
Route::post('/accounts/{id}/portal-access', [AccountController::class, 'portalAccess'])->whereNumber('id');
Route::get('/accounts/{id}/360', [CrmParityController::class, 'account360'])->middleware('fieldsec:account')->whereNumber('id');
Route::get('/accounts/{id}/notes', [CrmParityController::class, 'accountNotes'])->middleware('fieldsec:account')->whereNumber('id');
Route::post('/accounts/{id}/notes', [CrmParityController::class, 'storeAccountNote'])->middleware('fieldsec:account')->whereNumber('id');
Route::delete('/accounts/{id}/notes/{noteId}', [CrmParityController::class, 'destroyAccountNote'])->middleware('fieldsec:account')->whereNumber('id')->whereNumber('noteId');

Route::get('/consultation-statuses', [ConsultationStatusController::class, 'index']);
Route::get('/consultations', [ConsultationController::class, 'index']);
Route::post('/consultations', [ConsultationController::class, 'store']);
Route::patch('/consultations/{id}', [ConsultationController::class, 'update'])->whereNumber('id');
Route::put('/consultations/{id}', [ConsultationController::class, 'update'])->whereNumber('id');
Route::post('/consultations/{id}/convert-project', [CrmParityController::class, 'convertConsultation'])->whereNumber('id');

Route::apiResource('deals', DealController::class);
Route::patch('deals/{deal}/move', [DealController::class, 'move']);
Route::get('contacts/export', [AudienceController::class, 'exportContacts']);
Route::post('contacts/import', [AudienceController::class, 'importContacts']);
Route::post('contacts/merge', [AudienceController::class, 'mergeContacts']);
Route::apiResource('contacts', ContactController::class);
Route::apiResource('pipelines', PipelineController::class);
Route::get('pipelines/{pipeline}/kanban', [PipelineController::class, 'kanban']);
Route::get('sources', [SourceController::class, 'index']);
Route::post('sources', [SourceController::class, 'store']);
Route::get('activities', [ActivityController::class, 'index']);
Route::post('activities', [ActivityController::class, 'store']);
Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);

Route::get('timeline', [TimelineController::class, 'index']);
Route::post('timeline', [TimelineController::class, 'store']);
Route::get('reminders', [TimelineController::class, 'reminders']);
Route::post('timeline/{id}/complete', [TimelineController::class, 'complete'])->whereNumber('id');

Route::get('forecast', [ForecastController::class, 'show']);
Route::post('quotas', [ForecastController::class, 'storeQuota']);
Route::delete('quotas/{id}', [ForecastController::class, 'destroyQuota'])->whereNumber('id');

Route::get('templates', [OutreachController::class, 'templates']);
Route::post('templates', [OutreachController::class, 'storeTemplate']);
Route::patch('templates/{id}', [OutreachController::class, 'updateTemplate'])->whereNumber('id');
Route::delete('templates/{id}', [OutreachController::class, 'destroyTemplate'])->whereNumber('id');
Route::get('messages', [OutreachController::class, 'messages']);
Route::post('messages', [OutreachController::class, 'send']);
Route::get('sequences', [OutreachController::class, 'sequences']);
Route::post('sequences', [OutreachController::class, 'storeSequence']);
Route::post('sequences/run-due', [OutreachController::class, 'runDue']);
Route::post('sequences/{id}/enroll', [OutreachController::class, 'enroll'])->whereNumber('id');

Route::get('score-rules', [OutreachController::class, 'scoreRules']);
Route::post('score-rules', [OutreachController::class, 'storeScoreRule']);
Route::patch('score-rules/{id}', [OutreachController::class, 'updateScoreRule'])->whereNumber('id');
Route::delete('score-rules/{id}', [OutreachController::class, 'destroyScoreRule'])->whereNumber('id');
Route::get('automation-rules', [OutreachController::class, 'automationRules']);
Route::post('automation-rules', [OutreachController::class, 'storeAutomation']);
Route::delete('automation-rules/{id}', [OutreachController::class, 'destroyAutomation'])->whereNumber('id');

Route::get('tags', [AudienceController::class, 'tags']);
Route::post('tags', [AudienceController::class, 'storeTag']);
Route::get('tags/applied', [AudienceController::class, 'tagged']);
Route::post('tags/{id}/attach', [AudienceController::class, 'attachTag'])->whereNumber('id');
Route::delete('tags/{id}/attach', [AudienceController::class, 'detachTag'])->whereNumber('id');
Route::get('segments', [AudienceController::class, 'segments']);
Route::post('segments', [AudienceController::class, 'storeSegment']);
Route::get('segments/{id}/preview', [AudienceController::class, 'previewSegment'])->whereNumber('id');
Route::get('lists', [AudienceController::class, 'lists']);
Route::post('lists', [AudienceController::class, 'storeList']);
Route::patch('lists/{id}', [AudienceController::class, 'updateList'])->whereNumber('id');
Route::post('lists/{id}/members', [AudienceController::class, 'addMember'])->whereNumber('id');
Route::delete('lists/{id}/members', [AudienceController::class, 'removeMember'])->whereNumber('id');
Route::get('custom-fields', [AudienceController::class, 'fields']);
Route::post('custom-fields', [AudienceController::class, 'storeField']);
Route::delete('custom-fields/{id}', [AudienceController::class, 'destroyField'])->whereNumber('id');
Route::get('custom-field-values', [AudienceController::class, 'values']);
Route::put('custom-field-values', [AudienceController::class, 'saveValues']);
Route::post('accounts/merge', [AudienceController::class, 'mergeAccounts']);
Route::get('accounts/{id}/duplicates', [AudienceController::class, 'accountDuplicates'])->whereNumber('id');
Route::get('contacts/{id}/duplicates', [AudienceController::class, 'contactDuplicates'])->whereNumber('id');
Route::get('audit', [AudienceController::class, 'audit']);
Route::get('companies', [CrmExpansionController::class, 'companies']);
Route::post('companies', [CrmExpansionController::class, 'storeCompany']);
Route::get('companies/active', [CrmExpansionController::class, 'activeCompany']);
Route::post('companies/{id}/switch', [CrmExpansionController::class, 'switchCompany'])->whereNumber('id');
Route::get('currencies', [CrmExpansionController::class, 'currencies']);
Route::post('fx-rates', [CrmExpansionController::class, 'storeRate']);
Route::get('fx/convert', [CrmExpansionController::class, 'convert']);
Route::get('catalog-products', [CrmExpansionController::class, 'products']);
Route::post('catalog-products', [CrmExpansionController::class, 'storeProduct']);
Route::get('price-books', [CrmExpansionController::class, 'priceBooks']);
Route::post('price-books', [CrmExpansionController::class, 'storePriceBook']);
Route::post('deals/{deal}/quote', [CrmExpansionController::class, 'quote']);
Route::get('lead-forms', [CrmExpansionController::class, 'forms']);
Route::post('lead-forms', [CrmExpansionController::class, 'storeForm']);
Route::post('ai/assist', [CrmExpansionController::class, 'assist']);
Route::get('esign', [CrmExpansionController::class, 'envelopes']);
Route::post('esign', [CrmExpansionController::class, 'storeEnvelope']);
Route::post('esign/{envelope}/signers/{signer}/otp', [CrmExpansionController::class, 'requestOtp'])->whereNumber('envelope')->whereNumber('signer');
Route::post('esign/{envelope}/signers/{signer}/sign', [CrmExpansionController::class, 'sign'])->whereNumber('envelope')->whereNumber('signer');
Route::get('einvoices', [CrmExpansionController::class, 'invoices']);
Route::post('einvoices', [CrmExpansionController::class, 'storeInvoice']);
Route::post('einvoices/{id}/submit', [CrmExpansionController::class, 'submitInvoice'])->whereNumber('id');
Route::get('content-calendars', [CrmExpansionController::class, 'calendars']);
Route::post('content-calendars', [CrmExpansionController::class, 'storeCalendar']);
Route::post('content-calendars/{id}/items', [CrmExpansionController::class, 'storeItem'])->whereNumber('id');
Route::patch('content-items/{id}', [CrmExpansionController::class, 'updateItem'])->whereNumber('id');
Route::post('content-items/remind-due', [CrmExpansionController::class, 'remindDue']);
Route::post('content-items/{id}/publish', [CrmProductionController::class, 'publishItem'])->whereNumber('id');
Route::get('product-rules', [CrmProductionController::class, 'rules']);
Route::post('product-rules', [CrmProductionController::class, 'storeRule']);
Route::get('discount-nodes', [CrmProductionController::class, 'discounts']);
Route::post('discount-nodes', [CrmProductionController::class, 'storeDiscount']);
Route::get('quote-approvals', [CrmProductionController::class, 'approvals']);
Route::post('quote-approvals/{id}/decide', [CrmProductionController::class, 'decideApproval'])->whereNumber('id');
Route::post('touchpoints', [CrmProductionController::class, 'touchpoints']);
Route::post('campaign-spends', [CrmProductionController::class, 'storeSpend']);
Route::get('attribution/roi', [CrmProductionController::class, 'roi']);
Route::get('social-connectors', [CrmProductionController::class, 'connectors']);
Route::post('social-connectors', [CrmProductionController::class, 'storeConnector']);

Route::post('pipelines/{pipeline}/stages', [PipelineController::class, 'storeStage']);
Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'updateStage']);
Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'destroyStage']);
