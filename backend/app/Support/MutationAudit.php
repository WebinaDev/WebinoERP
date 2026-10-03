<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\OpsAuditLog;

class MutationAudit
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        ?int $userId,
        string $module,
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $changes = [],
    ): void {
        if (! Schema::hasTable('ops_audit_logs')) {
            return;
        }

        OpsAuditLog::query()->create([
            'module' => $module,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'changes' => $changes,
        ]);
    }
}
