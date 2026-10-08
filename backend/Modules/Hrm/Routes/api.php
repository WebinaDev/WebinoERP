<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrm\Http\Controllers\AttendanceController;
use Modules\Hrm\Http\Controllers\AttendanceNestedController;
use Modules\Hrm\Http\Controllers\EmployeeController;
use Modules\Hrm\Http\Controllers\LeaveController;
use Modules\Hrm\Http\Controllers\LeaveNestedController;
use Modules\Hrm\Http\Controllers\MePortalController;
use Modules\Hrm\Http\Controllers\PayrollController;
use Modules\Hrm\Http\Controllers\PayrollNestedController;
use Modules\Hrm\Http\Controllers\PerformanceController;
use Modules\Hrm\Http\Controllers\PerformanceNestedController;
use Modules\Hrm\Http\Controllers\RecruitmentController;
use Modules\Hrm\Http\Controllers\RecruitmentNestedController;
use Modules\Hrm\Http\Controllers\StaffNestedController;
use Modules\Hrm\Http\Controllers\TrainingController;
use Modules\Hrm\Http\Controllers\TrainingNestedController;
use Modules\Hrm\Http\Controllers\ShiftPlanningController;
use Modules\Hrm\Http\Controllers\OnboardingController;
use Modules\Hrm\Http\Controllers\ObjectiveController;
use Modules\Hrm\Http\Controllers\Review360Controller;
use Modules\Hrm\Http\Controllers\LmsController;
use Modules\Hrm\Http\Controllers\SuccessionController;
use Modules\Hrm\Http\Controllers\TimesheetController;
use Modules\Hrm\Http\Controllers\HrAnalyticsController;
use Modules\Hrm\Http\Controllers\MeSuiteController;
use Modules\Hrm\Http\Controllers\AttendanceDeviceController;
use Modules\Hrm\Http\Controllers\OffboardingController;
use Modules\Hrm\Http\Controllers\ApprovalFlowController;
use Modules\Hrm\Http\Controllers\CalendarFeedController;
use Modules\Hrm\Http\Controllers\StaffSpreadsheetController;
use Modules\Hrm\Http\Controllers\HrmSettingsController;

// Employee self-service portal
Route::prefix('me')->group(function () {
    Route::get('/', [MePortalController::class, 'me']);
    Route::get('notices', [MePortalController::class, 'notices']);
    Route::get('shift', [MePortalController::class, 'shift']);
    Route::get('attendance', [MePortalController::class, 'attendanceIndex']);
    Route::post('attendance', [MePortalController::class, 'attendancePunch']);
    Route::get('decrees', [MePortalController::class, 'decrees']);
    Route::get('dependents', [MePortalController::class, 'dependentsIndex']);
    Route::post('dependents', [MePortalController::class, 'dependentsStore']);
    Route::get('org-chart', [MePortalController::class, 'orgChart']);
    Route::get('profile', [MePortalController::class, 'profileShow']);
    Route::patch('profile', [MePortalController::class, 'profileUpdate']);
    Route::get('requests', [MePortalController::class, 'myRequests']);
    Route::post('requests', [MePortalController::class, 'myRequestsStore']);
    Route::get('documents', [MePortalController::class, 'myDocuments']);
    Route::post('documents', [MePortalController::class, 'myDocumentsStore']);
    Route::get('documents/{document}/download', [MePortalController::class, 'myDocumentDownload']);
    Route::post('documents/{document}/sign', [MeSuiteController::class, 'signDocument']);
    Route::get('shift-calendar', [MeSuiteController::class, 'shiftCalendar']);
    Route::get('onboarding', [MeSuiteController::class, 'onboarding']);
    Route::post('onboarding/tasks/{onboardingTask}/complete', [MeSuiteController::class, 'completeOnboardingTask']);
    Route::get('objectives', [MeSuiteController::class, 'objectives']);
    Route::patch('objectives/key-results/{keyResult}', [ObjectiveController::class, 'updateKeyResult']);
    Route::get('reviews-360', [MeSuiteController::class, 'reviews']);
    Route::post('reviews-360/ratings/{rating}/submit', [Review360Controller::class, 'submit']);
    Route::get('learning', [LmsController::class, 'myLearning']);
    Route::post('learning/lessons/{lesson}/complete', [LmsController::class, 'complete']);
    Route::get('timesheets', [TimesheetController::class, 'index']);
    Route::post('timesheets', [TimesheetController::class, 'store']);
    Route::post('timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::get('offboarding', [OffboardingController::class, 'mine']);
    Route::patch('offboarding/{offboarding}', [OffboardingController::class, 'update']);
    Route::post('offboarding/tasks/{offboardingTask}/complete', [OffboardingController::class, 'completeTask']);
    Route::get('calendar.ics', [CalendarFeedController::class, 'mine']);
    Route::get('calendar/feed', [CalendarFeedController::class, 'ensure']);
    Route::post('calendar/feed', [CalendarFeedController::class, 'ensure']);
    Route::post('calendar/feed/regenerate', [CalendarFeedController::class, 'regenerate']);
    Route::get('notification-channels', [HrmSettingsController::class, 'myChannels']);
    Route::put('notification-channels', [HrmSettingsController::class, 'myChannelsSave']);
});

Route::get('requests/inbox', [MePortalController::class, 'requestsInbox']);
Route::post('requests/leaves/{leaveRequest}/approve', [LeaveNestedController::class, 'requestApprove']);
Route::post('requests/leaves/{leaveRequest}/reject', [LeaveNestedController::class, 'requestReject']);
Route::post('requests/{hrmRequest}/approve', [MePortalController::class, 'requestApprove']);
Route::post('requests/{hrmRequest}/reject', [MePortalController::class, 'requestReject']);

// Nested parity routes (must be registered before flat apiResource captures segments)
Route::prefix('staff')->group(function () {
    Route::get('export', [StaffSpreadsheetController::class, 'export']);
    Route::get('import-template', [StaffSpreadsheetController::class, 'template']);
    Route::post('import', [StaffSpreadsheetController::class, 'import']);
    Route::get('{staff}/user-link', [StaffSpreadsheetController::class, 'userLink']);
    Route::get('/', [StaffNestedController::class, 'index']);
    Route::post('/', [StaffNestedController::class, 'store']);
    Route::delete('/{staff}', [StaffNestedController::class, 'destroy']);
    Route::get('/{staff}/profile', [StaffNestedController::class, 'getProfile']);
    Route::post('/{staff}/profile', [StaffNestedController::class, 'saveProfile']);
    Route::get('/{staff}/dependents', [StaffNestedController::class, 'dependentsIndex']);
    Route::post('/{staff}/dependents', [StaffNestedController::class, 'dependentsStore']);
    Route::get('/{staff}/documents', [StaffNestedController::class, 'documentsIndex']);
    Route::post('/{staff}/documents', [StaffNestedController::class, 'documentsStore']);
    Route::get('/{staff}/documents/{document}/download', [StaffNestedController::class, 'documentsDownload']);
    Route::post('/{staff}/documents/{document}/sign', [StaffNestedController::class, 'documentsSign']);
});
Route::get('org-positions', [StaffNestedController::class, 'orgPositionsIndex']);
Route::post('org-positions', [StaffNestedController::class, 'orgPositionStore']);
Route::delete('org-positions/{orgPosition}', [StaffNestedController::class, 'orgPositionDestroy']);

Route::post('attendance/check-in', [AttendanceNestedController::class, 'checkIn']);
Route::get('attendance/devices', [AttendanceDeviceController::class, 'index']);
Route::post('attendance/devices', [AttendanceDeviceController::class, 'store']);
Route::patch('attendance/devices/{device}', [AttendanceDeviceController::class, 'update']);
Route::post('attendance/devices/{device}/rotate-key', [AttendanceDeviceController::class, 'rotateKey']);
Route::delete('attendance/devices/{device}', [AttendanceDeviceController::class, 'destroy']);
Route::get('attendance/device-users', [AttendanceDeviceController::class, 'mappings']);
Route::post('attendance/device-users', [AttendanceDeviceController::class, 'mappingStore']);
Route::delete('attendance/device-users/{mapping}', [AttendanceDeviceController::class, 'mappingDestroy']);
Route::get('attendance/punches', [AttendanceDeviceController::class, 'punches']);
Route::post('attendance/punches/{punch}/assign', [AttendanceDeviceController::class, 'assignPunch']);
Route::post('attendance/import-csv', [AttendanceDeviceController::class, 'importCsv']);
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
    Route::get('my-payslips', [MePortalController::class, 'myPayslips']);
    Route::get('decrees', [MePortalController::class, 'payrollDecreesIndex']);
    Route::post('decrees', [MePortalController::class, 'payrollDecreesStore']);
    Route::get('runs', [PayrollNestedController::class, 'runsIndex']);
    Route::post('runs', [PayrollNestedController::class, 'runStore']);
    Route::get('runs/{run}', [PayrollNestedController::class, 'runGet']);
    Route::post('runs/{run}/calculate', [PayrollNestedController::class, 'runCalculate']);
    Route::post('runs/{run}/approve', [PayrollNestedController::class, 'runApprove']);
    Route::post('runs/{run}/mark-paid', [PayrollNestedController::class, 'runMarkPaid']);
    Route::get('runs/{run}/insurance-list', [PayrollNestedController::class, 'insuranceList']);
    Route::get('insurance-list', [PayrollNestedController::class, 'insurancePeriod']);
    Route::get('runs/{run}/bank-export', [PayrollNestedController::class, 'bankExport']);
    Route::get('runs/{run}/payslips', [PayrollNestedController::class, 'payslipsList']);
    Route::get('payslip-items/{item}/pdf', [PayrollNestedController::class, 'payslipPdf']);
    Route::get('decrees/{decree}/pdf', [PayrollNestedController::class, 'decreePdf']);
    Route::post('severance-preview', [PayrollNestedController::class, 'severancePreview']);
    Route::post('payslips', [PayrollNestedController::class, 'payslipIssue']);
    Route::get('loans', [PayrollNestedController::class, 'loansIndex']);
    Route::post('loans', [PayrollNestedController::class, 'loansStore']);
    Route::post('loans/{hrmLoan}/settle', [PayrollNestedController::class, 'loansSettle']);
    Route::get('templates', [PayrollNestedController::class, 'templatesIndex']);
    Route::post('templates', [PayrollNestedController::class, 'templatesSave']);
    Route::get('templates/{slug}/render', [PayrollNestedController::class, 'templatesRender']);
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


Route::prefix('shifts')->group(function () {
    Route::get('templates', [ShiftPlanningController::class, 'templatesIndex']);
    Route::post('templates', [ShiftPlanningController::class, 'templatesStore']);
    Route::delete('templates/{shiftTemplate}', [ShiftPlanningController::class, 'templatesDestroy']);
    Route::get('rotations', [ShiftPlanningController::class, 'rotationsIndex']);
    Route::post('rotations', [ShiftPlanningController::class, 'rotationsStore']);
    Route::post('rotations/{rotation}/apply', [ShiftPlanningController::class, 'applyRotation']);
    Route::post('assignments', [ShiftPlanningController::class, 'assignmentsStore']);
    Route::get('calendar', [ShiftPlanningController::class, 'calendar']);
    Route::get('conflicts', [ShiftPlanningController::class, 'conflicts']);
});

Route::prefix('onboarding')->group(function () {
    Route::get('templates', [OnboardingController::class, 'templatesIndex']);
    Route::post('templates', [OnboardingController::class, 'templatesStore']);
    Route::get('/', [OnboardingController::class, 'index']);
    Route::post('start', [OnboardingController::class, 'start']);
    Route::post('tasks/{onboardingTask}/complete', [OnboardingController::class, 'completeTask']);
});

Route::prefix('objectives')->group(function () {
    Route::get('/', [ObjectiveController::class, 'index']);
    Route::post('/', [ObjectiveController::class, 'store']);
    Route::patch('key-results/{keyResult}', [ObjectiveController::class, 'updateKeyResult']);
});

Route::prefix('reviews-360')->group(function () {
    Route::get('/', [Review360Controller::class, 'index']);
    Route::post('/', [Review360Controller::class, 'store']);
    Route::post('ratings/{rating}/submit', [Review360Controller::class, 'submit']);
});

Route::prefix('succession')->group(function () {
    Route::get('chart', [SuccessionController::class, 'chart']);
    Route::post('plans', [SuccessionController::class, 'store']);
    Route::delete('plans/{successionPlan}', [SuccessionController::class, 'destroy']);
});

Route::prefix('timesheets')->group(function () {
    Route::get('/', [TimesheetController::class, 'index']);
    Route::post('/', [TimesheetController::class, 'store']);
    Route::post('{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::post('{timesheet}/decide', [TimesheetController::class, 'decide']);
});

Route::prefix('offboarding')->group(function () {
    Route::get('templates', [OffboardingController::class, 'templatesIndex']);
    Route::post('templates', [OffboardingController::class, 'templatesStore']);
    Route::put('templates/{offboardingTemplate}', [OffboardingController::class, 'templatesUpdate']);
    Route::delete('templates/{offboardingTemplate}', [OffboardingController::class, 'templatesDestroy']);
    Route::get('/', [OffboardingController::class, 'index']);
    Route::post('start', [OffboardingController::class, 'start']);
    Route::post('tasks/{offboardingTask}/complete', [OffboardingController::class, 'completeTask']);
    Route::patch('tasks/{offboardingTask}', [OffboardingController::class, 'updateTask']);
    Route::patch('{offboarding}', [OffboardingController::class, 'update']);
    Route::delete('{offboarding}', [OffboardingController::class, 'destroy']);
});

Route::prefix('approval-flows')->group(function () {
    Route::get('/', [ApprovalFlowController::class, 'index']);
    Route::get('users', [ApprovalFlowController::class, 'users']);
    Route::post('/', [ApprovalFlowController::class, 'store']);
    Route::delete('{flow}', [ApprovalFlowController::class, 'destroy']);
});

Route::prefix('settings')->group(function () {
    Route::get('notifications', [HrmSettingsController::class, 'notifications']);
    Route::post('notifications', [HrmSettingsController::class, 'notificationsSave']);
    Route::post('notifications/test', [HrmSettingsController::class, 'notificationsTest']);
});

Route::get('calendar/employees/{employee}/ics', [CalendarFeedController::class, 'employeeIcs']);

Route::get('analytics/summary', [HrAnalyticsController::class, 'summary']);
Route::get('analytics/budgets', [HrAnalyticsController::class, 'budgets']);
Route::post('analytics/budgets', [HrAnalyticsController::class, 'budgetsStore']);
Route::delete('analytics/budgets/{budget}', [HrAnalyticsController::class, 'budgetsDestroy']);

Route::prefix('training')->group(function () {
    Route::get('courses', [TrainingNestedController::class, 'coursesIndex']);
    Route::post('courses', [TrainingNestedController::class, 'coursesStore']);
    Route::get('sessions', [TrainingNestedController::class, 'sessionsIndex']);
    Route::post('sessions', [TrainingNestedController::class, 'sessionsStore']);
    Route::get('enrollments', [TrainingNestedController::class, 'enrollmentsIndex']);
    Route::post('enrollments', [TrainingNestedController::class, 'enrollmentsStore']);
    Route::get('lessons', [LmsController::class, 'lessonsIndex']);
    Route::post('lessons', [LmsController::class, 'lessonsStore']);
    Route::post('lessons/{lesson}/complete', [LmsController::class, 'complete']);
    Route::post('enrollments/{enrollment}/certificate', [LmsController::class, 'issueCertificate']);
    Route::get('certificates/{certificate}', [LmsController::class, 'certificateShow']);
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
