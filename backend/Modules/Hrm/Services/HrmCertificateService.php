<?php

namespace Modules\Hrm\Services;

use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Hrm\Entities\HrmLessonProgress;
use Modules\Hrm\Entities\HrmTrainingCertificate;
use Modules\Hrm\Entities\HrmTrainingEnrollment;
use Modules\Hrm\Entities\HrmTrainingLesson;

class HrmCertificateService
{
    public function __construct(
        private readonly PdfGeneratorService $pdf,
        private readonly HrmSerialService $serials,
    ) {}

    public function syncEnrollment(int $courseId, int $employeeId): ?HrmTrainingCertificate
    {
        $lessons = HrmTrainingLesson::query()->where('course_id', $courseId)->get();
        if ($lessons->isEmpty()) {
            return null;
        }
        $required = $lessons->where('is_required', true);
        $needed = $required->isNotEmpty() ? $required : $lessons;
        $done = HrmLessonProgress::query()
            ->where('employee_id', $employeeId)
            ->whereIn('lesson_id', $needed->pluck('id'))
            ->whereNotNull('completed_at')
            ->count();
        if ($done < $needed->count()) {
            return null;
        }

        $enrollment = HrmTrainingEnrollment::query()
            ->where('course_id', $courseId)
            ->where('employee_id', $employeeId)
            ->first();
        if ($enrollment && $enrollment->status !== 'completed') {
            $enrollment->update(['status' => 'completed']);
        }

        return $this->issue($courseId, $employeeId, $enrollment?->id);
    }

    public function issue(int $courseId, int $employeeId, ?int $enrollmentId = null, ?string $signer = null): HrmTrainingCertificate
    {
        $existing = HrmTrainingCertificate::query()
            ->where('course_id', $courseId)
            ->where('employee_id', $employeeId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $cert = new HrmTrainingCertificate([
            'course_id' => $courseId,
            'employee_id' => $employeeId,
            'enrollment_id' => $enrollmentId,
            'serial_no' => $this->serials->next('certificate'),
            'issued_at' => now(),
            'signer_name' => $signer ?: 'واحد آموزش',
        ]);
        $cert->html = $this->html($cert);
        $binary = $this->pdf->htmlToPdf($cert->html);
        if ($binary !== null) {
            $path = 'certificates/cert-'.$employeeId.'-'.$courseId.'-'.Str::random(6).'.pdf';
            Storage::disk('public')->put($path, $binary);
            $cert->pdf_path = $path;
        }
        $cert->save();

        return $cert->load(['course', 'employee']);
    }

    public function html(HrmTrainingCertificate $cert): string
    {
        $cert->loadMissing(['course', 'employee']);
        $name = trim(($cert->employee->first_name ?? '').' '.($cert->employee->last_name ?? ''));
        $course = e((string) ($cert->course->title ?? ''));
        $serial = e((string) $cert->serial_no);
        $when = optional($cert->issued_at)->toDateString() ?? now()->toDateString();
        $signer = e((string) ($cert->signer_name ?? 'واحد آموزش'));

        return <<<HTML
<div dir="rtl" style="font-family:tahoma,sans-serif;border:8px double #1e3a5f;padding:32px;max-width:800px;margin:auto">
  <h1 style="text-align:center">گواهینامه پایان دوره</h1>
  <p style="text-align:center">Certificate of completion</p>
  <p>بدین‌وسیله گواهی می‌شود <strong>{$name}</strong> دوره <strong>{$course}</strong> را با موفقیت به پایان رسانده است.</p>
  <p>شماره گواهی: {$serial}</p>
  <p>تاریخ صدور: {$when}</p>
  <div style="margin-top:48px;text-align:left">
    <div>امضا: {$signer}</div>
    <div style="min-height:48px;border-bottom:1px solid #333;width:180px"></div>
  </div>
</div>
HTML;
    }
}
