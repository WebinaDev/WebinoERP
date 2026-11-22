<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmCareerPath;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPerformanceAgreement;
use Modules\Hrm\Entities\HrmPerformanceCycle;
use Modules\Hrm\Entities\HrmPromotionProposal;
use Modules\Hrm\Entities\HrmReview360;
use Modules\Hrm\Entities\HrmReview360Rating;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Modules\Hrm\Entities\HrmTrainingEnrollment;
use Modules\Hrm\Services\HrmCareerProgressionService;
use Modules\Hrm\Services\HrmPerformanceManagementService;

class HrmTrainingAndPerformanceAppraisalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Test Training Course Registration & Effectiveness Evaluation
     */
    public function test_training_course_registration_and_effectiveness_evaluation(): void
    {
        $service = app(HrmPerformanceManagementService::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'TRN-01',
            'first_name' => 'سعید',
            'last_name' => 'کریمی',
            'base_salary' => 150000000,
            'is_active' => true,
        ]);

        $course = $service->registerTrainingCourse([
            'title' => 'دوره پیشرفته اصول حسابداری و مدیریت مالی',
            'description' => 'آموزش استانداردهای مالی و قوانین مالیات حقوق',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
        ]);

        $this->assertNotNull($course->id);
        $this->assertEquals('active', $course->status);

        // Pre-test: 40%, Post-test: 90%, Practical evaluation: 85%
        // Score = (90 * 0.4) + (85 * 0.4) + ((90-40) * 0.2) = 36 + 34 + 10 = 80.0%
        $result = $service->recordTrainingEffectiveness(
            $course->id,
            $employee->id,
            40.0,
            90.0,
            85.0
        );

        $this->assertEquals(80.0, $result['effectiveness_score']);
        $this->assertEquals('satisfactory', $result['level']);
        $this->assertTrue($result['is_effective']);
        $this->assertDatabaseHas('hrm_training_enrollments', [
            'course_id' => $course->id,
            'employee_id' => $employee->id,
            'status' => 'completed',
        ]);
    }

    /**
     * 2. Test Performance Cycle (Goal Setting, Mid-term Evaluation, Final Evaluation)
     */
    public function test_performance_cycle_lifecycle_and_scoring(): void
    {
        $service = app(HrmPerformanceManagementService::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'PERF-01',
            'first_name' => 'ناهید',
            'last_name' => 'یزدانی',
            'base_salary' => 120000000,
            'is_active' => true,
        ]);

        $cycle = HrmPerformanceCycle::query()->create([
            'name' => 'ارزیابی جامع سالانه ۱۴۰۵',
            'start_date' => '2026-03-21',
            'end_date' => '2026-12-29',
            'status' => 'active',
        ]);

        // Stage 1: Goal setting
        $agreement = $service->initiateCycleAppraisal(
            $employee,
            '1405-H1',
            $cycle->id,
            [
                ['title' => 'تحویل به موقع ماژول‌های حسابداری', 'weight' => 2.0],
                ['title' => 'کاهش نرخ خطای ورود داده به زیر ۲ درصد', 'weight' => 1.5],
            ],
            'هدف‌گذاری اولیه نیمه اول سال'
        );

        $this->assertEquals('planning', $agreement->stage);

        // Stage 2: Mid-term evaluation
        $agreement = $service->recordMidTermEvaluation($agreement, 65.0, 'عملکرد رضایت‌بخش و پیشرفت مناسب');
        $this->assertEquals('monitoring', $agreement->stage);
        $this->assertStringContainsString('ارزیابی میانی: پیشرفت 65%', $agreement->manager_notes);

        // Stage 3: Final evaluation (score: 92/100)
        // Base salary = 120M -> Bonus = (92 - 80) / 20 = 12/20 = 60% of base = 72,000,000 IRR
        $agreement = $service->finalizeAgreement($agreement, 92.0, 'تحقق کامل اهداف و شایستگی بالا');
        $this->assertEquals('finalized', $agreement->stage);
        $this->assertEquals(92.0, (float) $agreement->final_score);
        $this->assertEquals(72000000.0, (float) $agreement->performance_bonus_suggested);
    }

    /**
     * 3. Test 360-Degree Review Structure and Multi-Rater Aggregation
     */
    public function test_360_degree_review_multi_rater_skeleton(): void
    {
        $service = app(HrmPerformanceManagementService::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'REV-360-01',
            'first_name' => 'امیر',
            'last_name' => 'کاظمیان',
            'base_salary' => 110000000,
            'is_active' => true,
        ]);

        $rater1 = HrmEmployee::query()->create(['employee_code' => 'RATER-1', 'first_name' => 'همکار', 'last_name' => 'اول', 'base_salary' => 90000000]);
        $rater2 = HrmEmployee::query()->create(['employee_code' => 'RATER-2', 'first_name' => 'مدیر', 'last_name' => 'مستقیم', 'base_salary' => 180000000]);

        $review = $service->create360Review($employee, null, [
            ['employee_id' => $rater1->id, 'relationship' => 'peer'],
            ['employee_id' => $rater2->id, 'relationship' => 'manager'],
        ]);

        $this->assertEquals('open', $review->status);
        $this->assertCount(2, $review->ratings);

        $rating1 = $review->ratings->firstWhere('rater_employee_id', $rater1->id);
        $rating2 = $review->ratings->firstWhere('rater_employee_id', $rater2->id);

        // Submit first score: 80
        $review = $service->submit360Rating($rating1, 80, 'همکاری عالی در تیم');
        $this->assertEquals('open', $review->status);
        $this->assertEquals(80.0, (float) $review->average_score);

        // Submit second score: 90 => Average = (80 + 90) / 2 = 85.0
        $review = $service->submit360Rating($rating2, 90, 'تعهد سازمانی و مدیریت زمان مناسب');
        $this->assertEquals('completed', $review->status);
        $this->assertEquals(85.0, (float) $review->average_score);
    }

    /**
     * 4. Test Connecting Performance Evaluation to Career Progression & Promotion
     */
    public function test_link_performance_results_to_career_progression(): void
    {
        $service = app(HrmPerformanceManagementService::class);

        // Seed career path levels
        HrmCareerPath::query()->create([
            'job_family' => 'تخصصی',
            'level' => 1,
            'title_fa' => 'کارشناس سطح ۱',
            'min_tenure_months' => 12,
            'min_performance_score' => 75.0,
            'base_salary_multiplier' => 1.0,
        ]);

        HrmCareerPath::query()->create([
            'job_family' => 'تخصصی',
            'level' => 2,
            'title_fa' => 'کارشناس ارشد سطح ۲',
            'min_tenure_months' => 24,
            'min_performance_score' => 80.0,
            'base_salary_multiplier' => 1.25,
        ]);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'CAREER-01',
            'first_name' => 'محسن',
            'last_name' => 'صالحی',
            'rank_level' => 1,
            'hire_date' => now()->subMonths(30), // 30 months tenure (> 24)
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        // Link performance with score 88.0% (exceeds min_performance_score 80.0)
        $proposal = $service->linkPerformanceToCareerProgression($employee, 88.0);

        $this->assertNotNull($proposal);
        $this->assertEquals('pending_review', $proposal->status);
        $this->assertGreaterThanOrEqual(80.0, $proposal->eligibility_score);

        // Career progression approving proposal and issuing decree
        $progressionService = app(HrmCareerProgressionService::class);
        $decree = $progressionService->approveProposalAndIssueDecree($proposal);

        $this->assertNotNull($decree);
        $this->assertEquals(125000000.0, (float) $decree->base_salary); // 100M * 1.25 = 125,000,000 IRR
        $this->assertEquals(2, $employee->fresh()->rank_level);
        $this->assertEquals('کارشناس ارشد سطح ۲', $employee->fresh()->position);
    }
}
