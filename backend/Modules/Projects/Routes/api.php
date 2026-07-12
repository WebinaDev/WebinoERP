<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\AppointmentController;
use Modules\Projects\Http\Controllers\ContractController;
use Modules\Projects\Http\Controllers\FormController;
use Modules\Projects\Http\Controllers\KanbanParityController;
use Modules\Projects\Http\Controllers\ProjectController;
use Modules\Projects\Http\Controllers\ProjectInvoiceController;
use Modules\Projects\Http\Controllers\ProjectProductController;
use Modules\Projects\Http\Controllers\SprintController;
use Modules\Projects\Http\Controllers\SubscriptionController;
use Modules\Projects\Http\Controllers\TaskController;
use Modules\Projects\Http\Controllers\TicketController;
use Modules\Projects\Http\Controllers\TimeTrackingController;
use Modules\Projects\Http\Controllers\WorkflowController;

/*
|--------------------------------------------------------------------------
| API — /api/v1/projects (parity with webinocrm AJAX → REST inventory)
|--------------------------------------------------------------------------
*/

Route::post('/tickets/{id}/rating', [TicketController::class, 'rating'])->whereNumber('id');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/forms', [FormController::class, 'index']);
    Route::post('/forms', [FormController::class, 'store']);
    Route::get('/forms/{id}', [FormController::class, 'show'])->whereNumber('id');
    Route::patch('/forms/{id}', [FormController::class, 'update'])->whereNumber('id');
    Route::delete('/forms/{id}', [FormController::class, 'destroy'])->whereNumber('id');

    Route::get('/project-templates', [ProjectController::class, 'templates']);
    Route::get('/assignable-users', [ProjectController::class, 'assignableUsers']);

    Route::get('/projects', [ProjectController::class, 'index'])->middleware('fieldsec:project');
    Route::get('/projects/export', [ProjectController::class, 'export']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('fieldsec:project');
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
    Route::patch('/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');
    Route::get('/projects/{id}/assignees', [ProjectController::class, 'assignees'])->whereNumber('id');
    Route::get('/projects/{id}/details', [ProjectController::class, 'details'])->whereNumber('id');

    Route::get('/contracts', [ContractController::class, 'index'])->middleware('fieldsec:contract');
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts/{id}', [ContractController::class, 'show'])->whereNumber('id');
    Route::put('/contracts/{id}', [ContractController::class, 'update'])->whereNumber('id');
    Route::patch('/contracts/{id}', [ContractController::class, 'update'])->whereNumber('id');
    Route::delete('/contracts/{id}', [ContractController::class, 'destroy'])->whereNumber('id');
    Route::post('/contracts/{id}/cancel', [ContractController::class, 'cancel'])->whereNumber('id');
    Route::post('/contracts/{id}/projects', [ContractController::class, 'addProject'])->whereNumber('id');
    Route::post('/contracts/from-product', [ContractController::class, 'addServicesFromProduct']);
    Route::get('/contracts/{id}/details', [ContractController::class, 'details'])->whereNumber('id');
    Route::post('/contracts/{id}/pdf', [ContractController::class, 'pdf'])->whereNumber('id');
    Route::post('/contracts/{id}/email', [ContractController::class, 'email'])->whereNumber('id');

    Route::get('/products/{id}/projects-preview', [ProjectController::class, 'productProjectsPreview'])->whereNumber('id');

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id');
    Route::patch('/tickets/{id}', [TicketController::class, 'update'])->whereNumber('id');
    Route::post('/tickets/{id}/replies', [TicketController::class, 'reply'])->whereNumber('id');
    Route::post('/tickets/{id}/convert-task', [TicketController::class, 'convertTask'])->whereNumber('id');

    Route::get('/tasks', [TaskController::class, 'index'])->middleware('fieldsec:task');
    Route::get('/tasks/search', [TaskController::class, 'search']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/quick', [TaskController::class, 'quick']);
    Route::patch('/tasks/bulk', [TaskController::class, 'bulkEdit']);
    Route::get('/tasks/calendar', [TaskController::class, 'calendar']);
    Route::get('/tasks/gantt', [TaskController::class, 'gantt']);
    Route::post('/task-templates', [TaskController::class, 'saveAsTemplate']);
    Route::patch('/tasks/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id');
    Route::patch('/tasks/{id}/assignee', [TaskController::class, 'updateAssignee'])->whereNumber('id');
    Route::post('/tasks/{id}/comments', [TaskController::class, 'addComment'])->whereNumber('id');
    Route::patch('/tasks/{id}/content', [TaskController::class, 'saveContent'])->whereNumber('id');
    Route::post('/tasks/{id}/checklist', [TaskController::class, 'manageChecklist'])->whereNumber('id');
    Route::post('/tasks/{id}/time-logs', [TaskController::class, 'logTime'])->whereNumber('id');
    Route::post('/tasks/{id}/links', [TaskController::class, 'addLink'])->whereNumber('id');
    Route::delete('/tasks/{taskId}/attachments/{attachmentId}', [TaskController::class, 'deleteAttachment'])
        ->whereNumber('taskId')
        ->whereNumber('attachmentId');
    Route::post('/tasks/{id}/attachments', [TaskController::class, 'uploadAttachment'])->whereNumber('id');
    Route::get('/tasks/{id}/attachments', [TaskController::class, 'attachments'])->whereNumber('id');
    Route::delete('/tasks/{id}/links/{linkId}', [TaskController::class, 'removeLink'])
        ->whereNumber('id')
        ->whereNumber('linkId');

    Route::post('/time-entries/start', [TimeTrackingController::class, 'start']);
    Route::post('/time-entries/stop', [TimeTrackingController::class, 'stop']);
    Route::post('/time-entries/pause', [TimeTrackingController::class, 'pause']);
    Route::post('/time-entries/resume', [TimeTrackingController::class, 'resume']);
    Route::post('/time-entries/manual', [TimeTrackingController::class, 'addManual']);
    Route::get('/time-entries', [TimeTrackingController::class, 'index']);
    Route::delete('/time-entries/{id}', [TimeTrackingController::class, 'destroy'])->whereNumber('id');
    Route::get('/time-entries/report', [TimeTrackingController::class, 'report']);
    Route::get('/time-entries/active', [TimeTrackingController::class, 'active']);

    Route::get('/sprints', [SprintController::class, 'index']);
    Route::post('/sprints', [SprintController::class, 'store']);
    Route::patch('/sprints/{id}', [SprintController::class, 'update'])->whereNumber('id');
    Route::delete('/sprints/{id}', [SprintController::class, 'destroy'])->whereNumber('id');

    Route::get('/sprints/backlog', [SprintController::class, 'backlog']);
    Route::post('/sprints/{id}/tasks', [SprintController::class, 'addTask'])->whereNumber('id');
    Route::delete('/sprints/task/{taskId}', [SprintController::class, 'removeTask'])->whereNumber('taskId');
    Route::post('/sprints/{id}/start', [SprintController::class, 'start'])->whereNumber('id');
    Route::post('/sprints/{id}/finish', [SprintController::class, 'finish'])->whereNumber('id');

    Route::put('/workflow/status-order', [WorkflowController::class, 'saveStatusOrder']);
    Route::post('/workflow/statuses', [WorkflowController::class, 'addStatus']);
    Route::delete('/workflow/statuses/{id}', [WorkflowController::class, 'destroyStatus'])->whereNumber('id');

    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions/{id}/convert-contract', [SubscriptionController::class, 'convert'])->whereNumber('id');
    Route::get('/products', [ProjectProductController::class, 'index']);
    Route::put('/products/{id}/task-template', [ProjectProductController::class, 'updateTaskTemplate'])->whereNumber('id');
    Route::get('/task-templates', [ProjectProductController::class, 'taskTemplates']);

    Route::get('/invoices', [ProjectInvoiceController::class, 'index']);
    Route::post('/invoices', [ProjectInvoiceController::class, 'store']);
    Route::put('/invoices', [ProjectInvoiceController::class, 'update']);
    Route::get('/invoices/{id}', [ProjectInvoiceController::class, 'show'])->whereNumber('id');
    Route::delete('/invoices/{id}', [ProjectInvoiceController::class, 'destroy'])->whereNumber('id');
    Route::post('/invoices/{id}/pdf', [ProjectInvoiceController::class, 'pdf'])->whereNumber('id');
    Route::post('/invoices/{id}/email', [ProjectInvoiceController::class, 'sendEmail'])->whereNumber('id');

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::put('/appointments', [AppointmentController::class, 'update']);
    Route::get('/appointments/calendar', [AppointmentController::class, 'calendar']);
    Route::post('/appointments/quick', [AppointmentController::class, 'quickCreate']);
    Route::post('/appointments/request', [AppointmentController::class, 'clientRequest']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->whereNumber('id');
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy'])->whereNumber('id');
    Route::patch('/appointments/{id}/date', [AppointmentController::class, 'updateDate'])->whereNumber('id');

    Route::get('/kanban/data', [KanbanParityController::class, 'data']);
    Route::post('/kanban/cards', [KanbanParityController::class, 'createCard']);
    Route::patch('/kanban/cards/{id}', [KanbanParityController::class, 'updateCard'])->whereNumber('id');
    Route::delete('/kanban/cards/{id}', [KanbanParityController::class, 'deleteCard'])->whereNumber('id');
    Route::post('/kanban/columns', [KanbanParityController::class, 'createColumn']);
    Route::patch('/kanban/columns/{id}', [KanbanParityController::class, 'updateColumn'])->whereNumber('id');
    Route::delete('/kanban/columns/{id}', [KanbanParityController::class, 'deleteColumn'])->whereNumber('id');
});
