<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Hrm\Services\HrmStaffSpreadsheet;
use Modules\Hrm\Support\HrmAccess;
use Modules\Hrm\Support\SimpleSpreadsheet;

class StaffSpreadsheetController extends Controller
{
    public function export(Request $request, HrmStaffSpreadsheet $sheet): Response
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);

        return $this->file($sheet->rows(), $request->string('format', 'xlsx')->toString(), 'staff');
    }

    public function template(Request $request, HrmStaffSpreadsheet $sheet): Response
    {
        abort_unless(HrmAccess::canManageStaff(), 403);

        return $this->file($sheet->template(), $request->string('format', 'xlsx')->toString(), 'staff-import-template');
    }

    public function import(Request $request, HrmStaffSpreadsheet $sheet): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $request->validate([
            'file' => 'required|file|max:10240|extensions:csv,txt,xlsx',
            'dry_run' => 'nullable|boolean',
        ]);
        $file = $request->file('file');
        $result = $sheet->import($file->getRealPath(), $file->getClientOriginalName(), $request->boolean('dry_run'));

        return response()->json(['data' => $result]);
    }

    /**
     * Contract for WebinoDashboard. Linking an ERP employee to a dashboard/site user
     * is owned by WebinoDashboard. This ERP only exposes the current user_id.
     */
    public function userLink(\Modules\Hrm\Entities\HrmEmployee $staff): JsonResponse
    {
        if (! HrmAccess::seesAllStaff()) {
            abort_unless(HrmAccess::employee()?->id === $staff->id, 403);
        }

        return response()->json([
            'data' => [
                'employee_id' => $staff->id,
                'employee_code' => $staff->employee_code,
                'user_id' => $staff->user_id,
                'linked' => $staff->user_id !== null,
                'owner' => 'WebinoDashboard',
                'contract' => [
                    'read' => 'GET /api/v1/hrm/staff/{id}/user-link',
                    'write' => 'WebinoDashboard sets hrm_employees.user_id. Do not create site users from this module.',
                    'match' => ['employee_code', 'national_id', 'mobile', 'email'],
                ],
            ],
        ]);
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function file(array $rows, string $format, string $name): Response
    {
        if ($format === 'csv') {
            return response(SimpleSpreadsheet::toCsv($rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
            ]);
        }

        return response(SimpleSpreadsheet::toXlsx($rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }
}
