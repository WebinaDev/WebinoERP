<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmAttritionRiskLog;
use Modules\Hrm\Entities\HrmCareerPath;
use Modules\Hrm\Entities\HrmDecreeFormulaComponent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Hrm\Entities\HrmJobPosting;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPromotionProposal;
use Modules\Hrm\Entities\HrmResumeAiMatch;
use Modules\Hrm\Entities\HrmRetireeRecord;
use Modules\Hrm\Services\HrmAttritionPredictionService;
use Modules\Hrm\Services\HrmCareerProgressionService;
use Modules\Hrm\Services\HrmFormulaEvaluationService;
use Modules\Hrm\Services\HrmLegalDisketteService;
use Modules\Hrm\Services\HrmRecruitmentAiService;
use Modules\Hrm\Services\HrmRetroactivePayrollService;

class HrmEnterpriseSuiteController extends Controller
{
    // ── 1. Retroactive Payroll (معوقات حقوق و مابه‌التفاوت احکام جدید) ──

    public function retroAdjustments(Request $request): JsonResponse
    {
        $query = HrmPayrollRetroAdjustment::query()->with(['employee', 'decree', 'payrollRun']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate(20),
        ]);
    }

    public function calculateRetroForDecree(int $decreeId, HrmRetroactivePayrollService $service): JsonResponse
    {
        $decree = HrmEmploymentDecree::query()->with('employee')->findOrFail($decreeId);
        $adjustments = $service->calculateRetroDifferentials($decree);

        return response()->json([
            'message' => 'محاسبه مابه‌التفاوت عطف‌به‌ماسبق با موفقیت انجام شد.',
            'count' => count($adjustments),
            'data' => $adjustments,
        ]);
    }

    public function applyRetroAdjustment(int $id): JsonResponse
    {
        $adj = HrmPayrollRetroAdjustment::query()->findOrFail($id);
        $adj->update(['status' => 'applied']);

        return response()->json([
            'message' => 'مابه‌التفاوت با موفقیت جهت واریز در فیش حقوقی دوره بعدی منظور گردید.',
            'data' => $adj->fresh(),
        ]);
    }

    // ── 2. AI Resume Matching (امتیازدهی هوشمند متقاضیان استخدام) ──

    public function resumeMatches(Request $request): JsonResponse
    {
        $query = HrmResumeAiMatch::query()->with(['applicant', 'posting']);

        if ($request->filled('posting_id')) {
            $query->where('posting_id', $request->input('posting_id'));
        }
        if ($request->filled('applicant_id')) {
            $query->where('applicant_id', $request->input('applicant_id'));
        }

        return response()->json([
            'data' => $query->orderByDesc('overall_score')->paginate(20),
        ]);
    }

    public function runAiResumeMatch(int $applicantId, int $postingId, HrmRecruitmentAiService $service): JsonResponse
    {
        $applicant = HrmJobApplicant::query()->findOrFail($applicantId);
        $posting = HrmJobPosting::query()->findOrFail($postingId);

        $match = $service->analyzeAndScoreApplicant($applicant, $posting);

        return response()->json([
            'message' => 'ارزیابی هوش مصنوعی با موفقیت انجام شد.',
            'data' => $match->load(['applicant', 'posting']),
        ]);
    }

    // ── 3. Predictive Attrition Risk (پیش‌بینی و تحلیل خطر ترک خدمت پرسنل) ──

    public function attritionRisks(Request $request): JsonResponse
    {
        $query = HrmAttritionRiskLog::query()->with('employee');

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        return response()->json([
            'data' => $query->orderByDesc('risk_probability')->paginate(20),
        ]);
    }

    public function evaluateEmployeeAttrition(int $employeeId, HrmAttritionPredictionService $service): JsonResponse
    {
        $employee = HrmEmployee::query()->findOrFail($employeeId);
        $log = $service->evaluateEmployeeRisk($employee);

        return response()->json([
            'message' => 'ارزیابی ریسک خروج پرسنل با موفقیت انجام شد.',
            'data' => $log->load('employee'),
        ]);
    }

    // ── 4. Rahkaran Parity: Decree Formula Components (عوامل حکمی و فرمول‌نویسی) ──

    public function formulaComponents(): JsonResponse
    {
        return response()->json([
            'data' => HrmDecreeFormulaComponent::query()->orderBy('id')->get(),
        ]);
    }

    public function saveFormulaComponent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'title' => 'required|string|max:120',
            'component_type' => 'required|string|in:earning,deduction,benefit',
            'calculation_type' => 'required|string|in:fixed,formula,table_lookup',
            'formula_expression' => 'nullable|string',
            'is_taxable' => 'boolean',
            'is_insurable' => 'boolean',
        ]);

        $item = HrmDecreeFormulaComponent::query()->updateOrCreate(
            ['code' => $validated['code']],
            $validated
        );

        return response()->json([
            'message' => 'عامل حکمی با موفقیت ذخیره شد.',
            'data' => $item,
        ]);
    }

    // ── 5. Rahkaran Parity: Career Paths & Promotions (مسیر شغلی و ترفیع) ──

    public function careerPaths(): JsonResponse
    {
        return response()->json([
            'data' => HrmCareerPath::query()->orderBy('level')->get(),
        ]);
    }

    public function promotionProposals(): JsonResponse
    {
        return response()->json([
            'data' => HrmPromotionProposal::query()->with(['employee', 'targetCareerPath'])->orderByDesc('id')->get(),
        ]);
    }

    public function evaluatePromotion(int $employeeId, HrmCareerProgressionService $service): JsonResponse
    {
        $employee = HrmEmployee::query()->findOrFail($employeeId);
        $proposal = $service->evaluatePromotionEligibility($employee);

        return response()->json([
            'message' => $proposal ? 'پیشنهاد ارتقای شغلی با موفقیت صادر گردید.' : 'کارمند در حال حاضر حائز شرایط ارتقا نیست.',
            'data' => $proposal?->load(['employee', 'targetCareerPath']),
        ]);
    }

    // ── 6. Rahkaran Parity: SSO / Tax Diskette Generator (دیسکت بیمه و مالیات) ──

    public function generateSsoDiskette(int $runId, HrmLegalDisketteService $service): JsonResponse
    {
        $run = HrmPayrollRun::query()->findOrFail($runId);
        $result = $service->generateSsoDiskette($run);

        return response()->json([
            'message' => 'فایل دیسکت استاندارد تأمین اجتماعی با موفقیت تولید شد.',
            'data' => $result,
        ]);
    }

    // ── 7. Rahkaran Parity: Retiree & Pension Management (امور بازنشستگان) ──

    public function retirees(): JsonResponse
    {
        return response()->json([
            'data' => HrmRetireeRecord::query()->with('employee')->orderByDesc('retirement_date')->get(),
        ]);
    }
}
