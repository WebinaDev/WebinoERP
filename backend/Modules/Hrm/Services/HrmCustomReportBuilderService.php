<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmCustomReportDefinition;
use Modules\Hrm\Entities\HrmDataAccessAuditLog;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;

class HrmCustomReportBuilderService
{
    /**
     * Execute custom dynamic report query based on schema definition
     *
     * @return array{
     *   report_title: string,
     *   total_rows: int,
     *   columns: list<string>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function generateReport(HrmCustomReportDefinition $definition, ?int $userId = null): array
    {
        $columns = $definition->selected_columns ?: ['employee_code', 'first_name', 'last_name', 'base_salary'];
        $category = $definition->category;

        $rows = [];

        if ($category === 'payroll') {
            $items = HrmPayrollItem::query()->with('employee')->latest('id')->limit(500)->get();
            foreach ($items as $item) {
                $emp = $item->employee;
                $row = [];
                foreach ($columns as $col) {
                    $row[$col] = match ($col) {
                        'employee_code' => $emp?->employee_code,
                        'full_name' => ($emp?->first_name . ' ' . $emp?->last_name),
                        'national_id' => $emp?->national_id,
                        'gross' => (float) $item->gross,
                        'net' => (float) $item->net,
                        'tax' => (float) $item->tax,
                        'insurance' => (float) $item->employee_insurance,
                        default => $item->{$col} ?? $emp?->{$col} ?? null,
                    };
                }
                $rows[] = $row;
            }
        } else {
            // General workforce & employee directory
            $employees = HrmEmployee::query()->where('is_active', true)->limit(500)->get();
            foreach ($employees as $emp) {
                $row = [];
                foreach ($columns as $col) {
                    $row[$col] = match ($col) {
                        'full_name' => ($emp->first_name . ' ' . $emp->last_name),
                        default => $emp->{$col} ?? null,
                    };
                }
                $rows[] = $row;
            }
        }

        // Audit confidential report access
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_data_access_audit_logs')) {
            HrmDataAccessAuditLog::query()->create([
                'user_id' => $userId,
                'entity_type' => 'HrmCustomReportDefinition',
                'entity_id' => $definition->id,
                'access_type' => 'salary_export',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
                'accessed_fields' => $columns,
            ]);
        }

        return [
            'report_title' => $definition->title_fa,
            'total_rows' => count($rows),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }
}
