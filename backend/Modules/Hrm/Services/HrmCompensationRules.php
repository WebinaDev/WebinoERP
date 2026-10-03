<?php

namespace Modules\Hrm\Services;

use Illuminate\Validation\ValidationException;
use Modules\Hrm\Entities\HrmEmployee;

/**
 * Engagement types and legal wage floors.
 *
 * Full-time (تمام‌وقت) monthly/daily/hourly pay cannot sit under the configured
 * annual minima. Contractor, freelance, project and remote engagements are recorded
 * as-is and are NOT forced onto the full-time floor unless their code is listed in
 * the payroll setting min_wage_engagements (default: full_time only).
 * A minimum of 0 means "not configured yet" and does not block saves.
 */
class HrmCompensationRules
{
    public const ENGAGEMENTS = ['full_time', 'part_time', 'contractor', 'freelance', 'project', 'remote'];

    public const PAY_BASES = ['monthly', 'daily', 'hourly', 'project_fee'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyDefaults(array $data): array
    {
        $engagement = (string) ($data['engagement_type'] ?? 'full_time');
        if (! in_array($engagement, self::ENGAGEMENTS, true)) {
            $engagement = 'full_time';
        }
        $data['engagement_type'] = $engagement;

        $presets = [
            'full_time' => ['pay_basis' => 'monthly', 'insurance_applicable' => true, 'tax_applicable' => true],
            'part_time' => ['pay_basis' => 'monthly', 'insurance_applicable' => true, 'tax_applicable' => true],
            'remote' => ['pay_basis' => 'monthly', 'insurance_applicable' => true, 'tax_applicable' => true],
            'contractor' => ['pay_basis' => 'monthly', 'insurance_applicable' => true, 'tax_applicable' => true],
            'freelance' => ['pay_basis' => 'project_fee', 'insurance_applicable' => false, 'tax_applicable' => true],
            'project' => ['pay_basis' => 'project_fee', 'insurance_applicable' => false, 'tax_applicable' => true],
        ];
        $preset = $presets[$engagement];
        $data['pay_basis'] = $data['pay_basis'] ?? $preset['pay_basis'];
        if (! in_array($data['pay_basis'], self::PAY_BASES, true)) {
            $data['pay_basis'] = $preset['pay_basis'];
        }
        if (! array_key_exists('insurance_applicable', $data) || $data['insurance_applicable'] === null) {
            $data['insurance_applicable'] = $preset['insurance_applicable'];
        } else {
            $data['insurance_applicable'] = (bool) $data['insurance_applicable'];
        }
        if (! array_key_exists('tax_applicable', $data) || $data['tax_applicable'] === null) {
            $data['tax_applicable'] = $preset['tax_applicable'];
        } else {
            $data['tax_applicable'] = (bool) $data['tax_applicable'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function assertEmployee(HrmEmployee $employee, ?array $settings = null): void
    {
        $message = $this->violation($employee, $settings);
        if ($message !== null) {
            throw ValidationException::withMessages(['base_salary' => $message]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function violation(HrmEmployee $employee, ?array $settings = null): ?string
    {
        $settings ??= app(IranianPayrollCalculator::class)->settings();
        $engagement = (string) ($employee->engagement_type ?: 'full_time');
        $subject = $settings['min_wage_engagements'] ?? ['full_time'];
        if (! is_array($subject) || ! in_array($engagement, $subject, true)) {
            return null;
        }

        $basis = (string) ($employee->pay_basis ?: 'monthly');
        if ($basis === 'project_fee') {
            return null;
        }

        $minMonthly = (float) ($settings['minimum_monthly_wage'] ?? 0);
        $minDaily = (float) ($settings['minimum_daily_wage'] ?? 0);
        $minHourly = (float) ($settings['minimum_hourly_wage'] ?? 0);
        $days = max(1, (float) ($settings['working_days_per_month'] ?? 30));
        $hours = max(1, (float) ($settings['monthly_hours'] ?? 220));
        if ($minDaily <= 0 && $minMonthly > 0) {
            $minDaily = $minMonthly / $days;
        }
        if ($minHourly <= 0 && $minDaily > 0) {
            $minHourly = ($minDaily * $days) / $hours;
        }

        if ($basis === 'daily') {
            if ($minDaily <= 0) {
                return null;
            }
            if ((float) ($employee->daily_rate ?? 0) + 0.0001 < $minDaily) {
                return 'Daily rate is below the configured legal daily minimum for this engagement.';
            }

            return null;
        }

        if ($basis === 'hourly') {
            if ($minHourly <= 0) {
                return null;
            }
            if ((float) ($employee->hourly_rate ?? 0) + 0.0001 < $minHourly) {
                return 'Hourly rate is below the configured legal hourly minimum for this engagement.';
            }

            return null;
        }

        if ($minMonthly <= 0) {
            return null;
        }
        if ((float) ($employee->base_salary ?? 0) + 0.0001 < $minMonthly) {
            return 'Base salary is below the configured legal monthly minimum for full-time engagement.';
        }

        return null;
    }
}
