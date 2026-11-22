<?php

namespace Modules\Hrm\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Hrm\Entities\HrmCareerPath;

class HrmCareerPathSeeder extends Seeder
{
    public function run(): void
    {
        if (HrmCareerPath::query()->count() > 0) {
            return;
        }

        HrmCareerPath::query()->insert([
            ['job_family' => 'تخصصی', 'level' => 1, 'title_fa' => 'کارشناس سطح ۱', 'min_tenure_months' => 12, 'min_performance_score' => 75.0, 'base_salary_multiplier' => 1.0, 'created_at' => now(), 'updated_at' => now()],
            ['job_family' => 'تخصصی', 'level' => 2, 'title_fa' => 'کارشناس ارشد سطح ۲', 'min_tenure_months' => 24, 'min_performance_score' => 80.0, 'base_salary_multiplier' => 1.25, 'created_at' => now(), 'updated_at' => now()],
            ['job_family' => 'تخصصی', 'level' => 3, 'title_fa' => 'سرپرست تخصصی سطح ۳', 'min_tenure_months' => 36, 'min_performance_score' => 85.0, 'base_salary_multiplier' => 1.50, 'created_at' => now(), 'updated_at' => now()],
            ['job_family' => 'تخصصی', 'level' => 4, 'title_fa' => 'مدیر دپارتمان سطح ۴', 'min_tenure_months' => 48, 'min_performance_score' => 90.0, 'base_salary_multiplier' => 1.80, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
