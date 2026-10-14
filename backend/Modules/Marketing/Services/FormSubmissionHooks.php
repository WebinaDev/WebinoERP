<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Hrm\Entities\HrmJobPosting;
use Modules\Marketing\Entities\MarketingForm;

/**
 * Hands public form submissions to the owning module after they are stored as Marketing submissions:
 * consultation → CRM consultation, proposal request → CRM lead, cooperation request → HR job applicant.
 * The full payload is always kept on the Marketing submission; records here carry a readable copy.
 */
class FormSubmissionHooks
{
    public const CONSULTATION_FORMS = ['consultation', 'consultation-booking'];

    public const PROPOSAL_FORM = 'proposal-request';

    public const COOPERATION_FORM = 'cooperation-request';

    /** General posting that collects open applications from /cooperation. */
    public const COOPERATION_POSTING = 'درخواست همکاری از سایت';

    public static function dispatch(MarketingForm $form, array $payload, ?string $page): void
    {
        $notes = self::notes($form, $payload, $page);
        if (in_array($form->slug, self::CONSULTATION_FORMS, true)) {
            self::consultation($payload, $notes);
        } elseif ($form->slug === self::PROPOSAL_FORM) {
            self::proposalLead($payload, $notes);
        } elseif ($form->slug === self::COOPERATION_FORM) {
            self::cooperationApplicant($payload, $notes);
        }
    }

    public static function notes(MarketingForm $form, array $payload, ?string $page): string
    {
        $fields = collect($form->fields ?? [])->filter(fn ($f) => is_array($f) && ! empty($f['name']));
        $labels = $fields->mapWithKeys(fn ($f) => [$f['name'] => $f['label'] ?? $f['name']]);
        $files = $fields->filter(fn ($f) => ($f['type'] ?? '') === 'file')->pluck('name')->all();

        return collect($payload)
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(function ($v, $k) use ($labels, $files) {
                if (in_array($k, $files, true)) {
                    $name = FormUploads::originalName((string) $v);
                    $v = ($name ?? 'فایل').' (پیوست: '.$v.')';
                }

                return ($labels[$k] ?? $k).': '.$v;
            })
            ->push('منبع: '.($page ?: 'سایت عمومی').' (فرم '.$form->slug.')')
            ->implode("\n");
    }

    /** @return array{0: string, 1: string} */
    private static function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $parts = explode(' ', $name, 2);

        return [mb_substr($parts[0] ?: 'بدون‌نام', 0, 100), mb_substr($parts[1] ?? '-', 0, 100)];
    }

    private static function str(array $payload, string $key, int $max): ?string
    {
        $v = trim((string) ($payload[$key] ?? ''));

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function consultation(array $payload, string $notes): void
    {
        $name = trim((string) ($payload['name'] ?? ''));
        CrmConsultation::query()->create([
            'title' => $name !== '' ? 'درخواست مشاوره: '.mb_substr($name, 0, 200) : 'درخواست مشاوره از سایت',
            'status' => 'new',
            'notes' => $notes,
        ]);
    }

    private static function proposalLead(array $payload, string $notes): void
    {
        [$first, $last] = self::splitName((string) ($payload['name'] ?? ''));
        $status = CrmStatus::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first()
            ?? CrmStatus::query()->create(['name' => 'جدید', 'color' => '#0054ff', 'sort_order' => 0, 'is_active' => true]);
        $topic = self::str($payload, 'project_title', 200) ?? 'درخواست پروپوزال از سایت';

        CrmLead::query()->create([
            'topic' => 'پروپوزال: '.$topic,
            'first_name' => $first,
            'last_name' => $last,
            'company' => self::str($payload, 'company', 150),
            'email' => self::str($payload, 'email', 150),
            'mobile' => self::str($payload, 'phone', 20) ?? '-',
            'status_id' => $status->id,
            'description' => $notes,
        ]);
    }

    private static function cooperationApplicant(array $payload, string $notes): void
    {
        [$first, $last] = self::splitName((string) ($payload['name'] ?? ''));
        $posting = HrmJobPosting::query()->firstOrCreate(
            ['title' => self::COOPERATION_POSTING],
            ['department' => 'همکاری', 'description' => 'درخواست‌های همکاری ثبت‌شده در صفحه /cooperation سایت', 'status' => 'open']
        );

        HrmJobApplicant::query()->create([
            'job_posting_id' => $posting->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => self::str($payload, 'email', 150),
            'mobile' => Str::limit(self::str($payload, 'phone', 20) ?? '', 20, ''),
            'status' => 'applied',
            'resume_notes' => $notes,
        ]);
    }
}
