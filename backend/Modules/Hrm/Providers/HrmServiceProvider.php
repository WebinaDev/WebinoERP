<?php

namespace Modules\Hrm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPersonnelDocument;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmRequest;

class HrmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Hrm';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::bind('staff', fn (string $value) => HrmEmployee::query()->findOrFail($value));
        Route::bind('run', fn (string $value) => HrmPayrollRun::query()->findOrFail($value));
        Route::bind('leaveRequest', fn (string $value) => HrmLeaveRequest::query()->findOrFail($value));
        Route::bind('orgPosition', fn (string $value) => HrmOrgPosition::query()->findOrFail($value));
        Route::bind('applicant', fn (string $value) => HrmJobApplicant::query()->findOrFail($value));
        Route::bind('hrmLoan', fn (string $value) => HrmLoan::query()->findOrFail($value));
        Route::bind('hrmRequest', fn (string $value) => HrmRequest::query()->findOrFail($value));
        Route::bind('decree', fn (string $value) => HrmEmploymentDecree::query()->findOrFail($value));
        Route::bind('item', fn (string $value) => HrmPayrollItem::query()->findOrFail($value));
        Route::bind('document', fn (string $value) => HrmPersonnelDocument::query()->findOrFail($value));

        Route::prefix('api/v1/hrm')
            ->middleware(['api', 'auth:sanctum', 'module:hrm', 'module.permission:hrm'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
