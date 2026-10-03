<?php

namespace Modules\Hrm\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmNotice;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmNotifier
{
    public static function notify(?int $employeeId, string $kind, string $title, string $body): void
    {
        if (Schema::hasTable('hrm_notices')) {
            $payload = [
                'title' => $title,
                'body' => $body,
                'is_active' => true,
                'date_from' => now()->toDateString(),
            ];

            if (Schema::hasColumn('hrm_notices', 'employee_id')) {
                $payload['employee_id'] = $employeeId;
            }
            if (Schema::hasColumn('hrm_notices', 'kind')) {
                $payload['kind'] = $kind;
                $dup = HrmNotice::query()->where('kind', $kind)->where('body', $body);
                if ($employeeId && Schema::hasColumn('hrm_notices', 'employee_id')) {
                    $dup->where('employee_id', $employeeId);
                }
                if ($dup->exists()) {
                    // still try external once for critical kinds below
                } else {
                    HrmNotice::query()->create($payload);
                }
            } else {
                HrmNotice::query()->create($payload);
            }
        }

        self::fanoutExternal($employeeId, $kind, $title, $body);
    }

    private static function fanoutExternal(?int $employeeId, string $kind, string $title, string $body): void
    {
        try {
            $settings = app(IranianPayrollCalculator::class)->settings();
            if (! ($settings['external_notifications_enabled'] ?? true)) {
                return;
            }
        } catch (\Throwable) {
            // settings table may be missing during early migrate
        }

        $externalKinds = ['leave_approved', 'salary_paid', 'missing_docs', 'request_approved', 'request_rejected', 'payroll_paid'];
        if (! in_array($kind, $externalKinds, true)) {
            return;
        }

        $userId = null;
        $phone = null;
        if ($employeeId && Schema::hasTable('hrm_employees')) {
            $emp = HrmEmployee::query()->find($employeeId);
            $userId = $emp?->user_id;
            $phone = $emp?->mobile;
        }

        // Core Integrations NotificationFanout (SMS / chat bridges incl. Bale-compatible)
        if (class_exists(\Modules\Integrations\Services\NotificationFanout::class)) {
            try {
                app(\Modules\Integrations\Services\NotificationFanout::class)->dispatch([
                    'title' => $title,
                    'body' => $body,
                    'priority' => in_array($kind, ['salary_paid', 'payroll_paid', 'missing_docs'], true) ? 'high' : 'normal',
                    'event_key' => 'hrm.'.$kind,
                    'user_id' => $userId,
                    'phone' => $phone,
                    'channels' => ['in_app', 'chat', 'sms'],
                ]);
            } catch (\Throwable $e) {
                Log::info('HrmNotifier fanout skipped: '.$e->getMessage());
            }
        }

        // Optional direct Bale DM when Business service is wired
        if ($userId && class_exists(\Modules\Integrations\Services\BaleBusinessService::class)) {
            try {
                app(\Modules\Integrations\Services\BaleBusinessService::class)
                    ->sendMessageToUser((int) $userId, trim($title."\n".$body));
            } catch (\Throwable $e) {
                Log::info('HrmNotifier Bale skipped: '.$e->getMessage());
            }
        }
    }

    public static function missingDocs(int $employeeId, string $docTitle): void
    {
        self::notify(
            $employeeId,
            'missing_docs',
            'مدارک ناقص',
            'مدرک «'.$docTitle.'» ناقص یا منقضی است. لطفاً در پورتال کارکنان بارگذاری کنید.'
        );
    }
}
