<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Services\FormSubmissionHooks;

/**
 * Marketing form definitions only (never pages, menus or builder documents).
 * firstOrCreate: a form edited in داشبورد ← بازاریابی ← فرم‌ها is never changed.
 * Builder «فرم» widgets submit into these by slug (widget setting «نامک فرم»).
 */
class MarketingFormsSeeder extends Seeder
{
    public function run(): void
    {
        $basic = [
            ['name' => 'name', 'label' => 'نام', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'تلفن', 'type' => 'tel', 'required' => true],
            ['name' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => false],
            ['name' => 'message', 'label' => 'شرح نیاز', 'type' => 'textarea', 'required' => true],
        ];
        $forms = [
            'consultation' => ['فرم مشاوره', $basic, 'پیام شما ثبت شد. به‌زودی برمی‌گردیم.'],
            'proposal' => ['درخواست پروپوزال', $basic, 'پیام شما ثبت شد. به‌زودی برمی‌گردیم.'],
            'newsletter' => ['خبرنامه', [['name' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => true]], 'پیام شما ثبت شد. به‌زودی برمی‌گردیم.'],
            'contact' => ['فرم تماس', self::contactFormFields(), 'پیام شما ثبت شد. به‌زودی برمی‌گردیم.'],
            'consultation-booking' => ['رزرو جلسه مشاوره', self::consultationFields(), 'درخواست شما ثبت شد. تیم وبینا در کوتاه‌ترین زمان با شما تماس می‌گیرد و زمان جلسه را هماهنگ می‌کند.'],
            FormSubmissionHooks::PROPOSAL_FORM => ['فرم درخواست پروپوزال', self::proposalFields(), 'درخواست پروپوزال شما ثبت شد. پس از ثبت درخواست، معمولاً طی ۱ تا ۳ روز کاری با شما تماس گرفته می‌شود یا پروپوزال اولیه ارسال می‌گردد.'],
            FormSubmissionHooks::COOPERATION_FORM => ['درخواست همکاری', self::cooperationFields(), "درخواست شما با موفقیت ثبت شد.\nتیم وبینا آن را بررسی می‌کند و در صورت تناسب، در کوتاه‌ترین زمان با شما تماس خواهد گرفت."],
        ];
        foreach ($forms as $slug => [$title, $fields, $success]) {
            MarketingForm::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'description' => $title.' از سایت وبینا',
                'fields' => $fields,
                'success_message' => $success,
                'published' => true,
            ]);
        }
    }

    private static function f(string $name, string $label, string $type = 'text', bool $required = false, array $options = [], ?int $step = null): array
    {
        return array_filter(['name' => $name, 'label' => $label, 'type' => $type, 'required' => $required, 'options' => $options ?: null, 'step' => $step], fn ($v) => $v !== null);
    }

    /** Group title row (no input; rendered by the builder form as a heading). */
    private static function group(string $name, string $label, ?int $step = null): array
    {
        return self::f($name, $label, 'heading', false, [], $step);
    }

    public static function contactFormFields(): array
    {
        return [
            self::f('name', 'نام و نام خانوادگی', 'text', true),
            self::f('phone', 'شماره تماس', 'tel', true),
            self::f('email', 'ایمیل', 'email'),
            self::f('company', 'نام کسب‌وکار'),
            self::f('topic', 'موضوع', 'select', false, ['مشاوره', 'پروژه جدید', 'پشتیبانی', 'همکاری']),
            self::f('message', 'پیام شما', 'textarea', true),
        ];
    }

    public static function consultationFields(): array
    {
        return [
            self::f('name', 'نام و نام خانوادگی', 'text', true),
            self::f('phone', 'شماره تماس', 'tel', true),
            self::f('email', 'ایمیل', 'email'),
            self::f('business', 'نام کسب‌وکار / برند'),
            self::f('industry', 'حوزه فعالیت'),
            self::f('stage', 'وضعیت فعلی', 'select', false, ['در حال راه‌اندازی', 'فعال اما نیاز به رشد', 'دارای سایت/سیستم اما نتیجه ضعیف', 'به دنبال بازطراحی کامل']),
            self::f('message', 'توضیح کوتاه درباره نیاز یا هدف شما', 'textarea'),
            self::f('preferred_time', 'زمان پیشنهادی برای جلسه'),
        ];
    }

    public static function proposalFields(): array
    {
        return [
            self::group('group_contact', 'اطلاعات تماس'),
            self::f('name', 'نام و نام خانوادگی', 'text', true),
            self::f('company', 'نام شرکت / برند'),
            self::f('phone', 'شماره تماس', 'tel', true),
            self::f('email', 'ایمیل', 'email', true),
            self::f('website', 'وب‌سایت (در صورت وجود)'),
            self::group('group_project', 'درباره پروژه'),
            self::f('project_title', 'عنوان پروژه (خلاصه)'),
            self::f('project_types', 'نوع پروژه', 'checkbox', false, ['طراحی و توسعه وب‌سایت', 'فروشگاه اینترنتی', 'استراتژی و مدل کسب‌وکار', 'هویت بصری و برندینگ', 'اتوماسیون و سیستم‌سازی', 'رشد و افزایش فروش', 'پروژه جامع (از ایده تا اجرا)', 'سایر']),
            self::f('stage', 'وضعیت فعلی کسب‌وکار / پروژه'),
            self::f('goal', 'هدف اصلی از این پروژه', 'text', true),
            self::f('details', 'توضیح کامل نیاز و انتظارات', 'textarea', true),
            self::f('references', 'آیا نمونه یا مرجعی مد نظرتان هست؟ (لینک یا توضیح)', 'textarea'),
            self::f('budget', 'بودجه تقریبی (اختیاری)'),
            self::f('timeline', 'زمان‌بندی مورد انتظار (اختیاری)'),
            self::f('attachment', 'فایل پیوست (در صورت نیاز: بریف، نمونه، مستندات)', 'file'),
        ];
    }

    public static function cooperationFields(): array
    {
        $areas = ['استراتژی و مدل کسب‌وکار', 'طراحی UI/UX و هویت بصری', 'طراحی و توسعه وب و نرم‌افزار', 'اتوماسیون و سیستم‌سازی', 'تولید محتوا و سئو', 'رشد و بازاریابی دیجیتال', 'مدیریت پروژه و هماهنگی مشتری', 'پشتیبانی و اجرای عملیات'];

        return [
            self::f('cooperation_type', 'نوع همکاری', 'radio', true, ['همکاری شغلی (تمام‌وقت / پاره‌وقت)', 'همکاری پروژه‌ای / فریلنسری', 'همکاری تخصصی / پیمانکاری', 'شراکت تجاری / نمایندگی', 'سایر پیشنهادها'], 1),
            self::f('availability', 'چقدر زمان می‌توانی اختصاص دهی؟', 'select', false, ['تمام‌وقت', 'پاره‌وقت', 'پروژه‌ای', 'منعطف'], 1),
            self::f('name', 'نام و نام خانوادگی', 'text', true, [], 2),
            self::f('phone', 'شماره تماس', 'tel', true, [], 2),
            self::f('email', 'ایمیل', 'email', true, [], 2),
            self::f('city', 'شهر محل سکونت', 'text', false, [], 2),
            self::f('profile_url', 'لینکدین / سایت شخصی / نمونه‌کار آنلاین', 'text', false, [], 2),
            self::f('referral', 'نحوه آشنایی با وبینا', 'text', false, [], 2),
            self::f('main_area', 'حوزه تخصص اصلی', 'select', true, $areas, 3),
            self::f('other_areas', 'تخصص‌های فرعی', 'text', false, [], 3),
            self::f('experience_years', 'سابقه کار (سال)', 'number', false, [], 3),
            self::f('level', 'سطح تخصص', 'select', false, ['جونیور', 'میانی', 'ارشد', 'متخصص'], 3),
            self::f('skills', 'ابزارها و مهارت‌های کلیدی', 'text', false, [], 3),
            self::f('experience', 'توضیح کوتاه درباره تجربه‌های مرتبط', 'textarea', true, [], 3),
            self::f('portfolio_link', 'آیا نمونه کار یا پروژه شاخص داری؟ (لینک)', 'text', false, [], 3),
            self::f('portfolio_file', 'آپلود نمونه کار', 'file', false, [], 3),
            self::f('motivation', 'چرا می‌خواهی با وبینا همکاری کنی؟', 'textarea', true, [], 4),
            self::f('value_add', 'چه ارزشی می‌توانی اضافه کنی؟', 'textarea', false, [], 4),
            self::f('salary_expectation', 'انتظار مالی تقریبی (اختیاری)', 'text', false, [], 4),
            self::f('start_date', 'زمان آمادگی برای شروع', 'text', false, [], 4),
            self::f('notes', 'توضیحات تکمیلی', 'textarea', false, [], 4),
        ];
    }
}
