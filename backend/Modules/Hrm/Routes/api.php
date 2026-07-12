<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrm\Http\Controllers\AttendanceController;
use Modules\Hrm\Http\Controllers\AttendanceNestedController;
use Modules\Hrm\Http\Controllers\EmployeeController;
use Modules\Hrm\Http\Controllers\LeaveController;
use Modules\Hrm\Http\Controllers\LeaveNestedController;
use Modules\Hrm\Http\Controllers\PayrollController;
use Modules\Hrm\Http\Controllers\PayrollNestedController;
use Modules\Hrm\Http\Controllers\PerformanceController;
use Modules\Hrm\Http\Controllers\PerformanceNestedController;
use Modules\Hrm\Http\Controllers\RecruitmentController;
use Modules\Hrm\Http\Controllers\RecruitmentNestedController;
use Modules\Hrm\Http\Controllers\StaffNestedController;
use Modules\Hrm\Http\Controllers\TrainingController;
use Modules\Hrm\Http\Controllers\TrainingNestedController;

// Nested parity routes (must be registered before flat apiResource captures segments)
Route::prefix('staff')->group(function () {
    Route::get('/', [StaffNestedController::class, 'index']);
    Route::post('/', [StaffNestedController::class, 'store']);
    Route::delete('/{staff}', [StaffNestedController::class, 'destroy']);
    Route::get('/{staff}/profile', [StaffNestedController::class, 'getProfile']);
    Route::post('/{staff}/profile', [StaffNestedController::class, 'saveProfile']);
});
Route::get('org-positions', [StaffNestedController::class, 'orgPositionsIndex']);
Route::post('org-positions', [StaffNestedController::class, 'orgPositionStore']);
Route::delete('org-positions/{orgPosition}', [StaffNestedController::class, 'orgPositionDestroy']);

Route::post('attendance/check-in', [AttendanceNestedController::class, 'checkIn']);
Route::post('attendance/check-out', [AttendanceNestedController::class, 'checkOut']);

Route::prefix('leave')->group(function () {
    Route::get('types', [LeaveNestedController::class, 'typesIndex']);
    Route::post('types', [LeaveNestedController::class, 'typesStore']);
    Route::get('requests', [LeaveNestedController::class, 'requestsIndex']);
    Route::post('requests', [LeaveNestedController::class, 'requestStore']);
    Route::post('requests/{leaveRequest}/approve', [LeaveNestedController::class, 'requestApprove']);
    Route::post('requests/{leaveRequest}/reject', [LeaveNestedController::class, 'requestReject']);
    Route::get('balances', [LeaveNestedController::class, 'balancesIndex']);
});

Route::prefix('payroll')->group(function () {
    Route::get('settings', [PayrollNestedController::class, 'settingsGet']);
    Route::post('settings', [PayrollNestedController::class, 'settingsSave']);
    Route::get('components', [PayrollNestedController::class, 'componentsIndex']);
    Route::post('components', [PayrollNestedController::class, 'componentsStore']);
    Route::get('employee-salaries', [PayrollNestedController::class, 'employeeSalariesGet']);
    Route::post('employee-salaries', [PayrollNestedController::class, 'employeeSalariesSave']);
    Route::get('runs', [PayrollNestedController::class, 'runsIndex']);
    Route::post('runs', [PayrollNestedController::class, 'runStore']);
    Route::get('runs/{run}', [PayrollNestedController::class, 'runGet']);
    Route::post('runs/{run}/calculate', [PayrollNestedController::class, 'runCalculate']);
    Route::post('runs/{run}/approve', [PayrollNestedController::class, 'runApprove']);
    Route::get('runs/{run}/payslips', [PayrollNestedController::class, 'payslipsList']);
});

Route::prefix('recruitment')->group(function () {
    Route::get('postings', [RecruitmentNestedController::class, 'postingsIndex']);
    Route::post('postings', [RecruitmentNestedController::class, 'postingsStore']);
    Route::get('applicants', [RecruitmentNestedController::class, 'applicantsIndex']);
    Route::post('applicants', [RecruitmentNestedController::class, 'applicantsStore']);
    Route::delete('applicants/{applicant}', [RecruitmentNestedController::class, 'applicantsDestroy']);
    Route::post('applicants/{applicant}/hire', [RecruitmentNestedController::class, 'applicantHire']);
    Route::get('interviews', [RecruitmentNestedController::class, 'interviewsIndex']);
    Route::post('interviews', [RecruitmentNestedController::class, 'interviewsStore']);
});

Route::prefix('performance')->group(function () {
    Route::get('kpi-templates', [PerformanceNestedController::class, 'kpiTemplatesIndex']);
    Route::post('kpi-templates', [PerformanceNestedController::class, 'kpiTemplatesStore']);
    Route::get('cycles', [PerformanceNestedController::class, 'cyclesIndex']);
    Route::post('cycles', [PerformanceNestedController::class, 'cyclesStore']);
    Route::get('reviews', [PerformanceNestedController::class, 'reviewsIndex']);
    Route::post('reviews', [PerformanceNestedController::class, 'reviewsStore']);
});

Route::prefix('training')->group(function () {
    Route::get('courses', [TrainingNestedController::class, 'coursesIndex']);
    Route::post('courses', [TrainingNestedController::class, 'coursesStore']);
    Route::get('sessions', [TrainingNestedController::class, 'sessionsIndex']);
    Route::post('sessions', [TrainingNestedController::class, 'sessionsStore']);
    Route::get('enrollments', [TrainingNestedController::class, 'enrollmentsIndex']);
    Route::post('enrollments', [TrainingNestedController::class, 'enrollmentsStore']);
});

// Legacy flat routes (frontend EntityCrudPage compatibility)
Route::apiResource('employees', EmployeeController::class);
Route::apiResource('attendance', AttendanceController::class)->except(['show']);
Route::apiResource('leave', LeaveController::class)->except(['show']);
Route::apiResource('payroll', PayrollController::class);
Route::post('payroll/{payroll}/items', [PayrollController::class, 'storeItem']);
Route::delete('payroll/{payroll}/items/{item}', [PayrollController::class, 'destroyItem']);
Route::apiResource('recruitment', RecruitmentController::class)->except(['show']);
Route::apiResource('performance', PerformanceController::class)->except(['show']);
Route::apiResource('training', TrainingController::class);
Route::post('training/{course}/enroll', [TrainingController::class, 'enroll']);
