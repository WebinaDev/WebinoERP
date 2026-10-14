<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmLead;
use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Marketing\Database\Seeders\MarketingFormsSeeder;
use Modules\Marketing\Database\Seeders\MarketingSiteSeeder;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingFormSubmission;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Services\FormSubmissionHooks;
use Tests\TestCase;

class MarketingFormHooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(MarketingFormsSeeder::class);
    }

    public function test_site_seeders_never_write_pages_or_menus(): void
    {
        $this->seed(MarketingSiteSeeder::class);
        $this->assertSame(0, MarketingPage::query()->count());
        $this->assertSame(0, MarketingMenu::query()->count());
    }

    public function test_forms_seeder_keeps_edited_forms(): void
    {
        $form = MarketingForm::query()->where('slug', FormSubmissionHooks::PROPOSAL_FORM)->firstOrFail();
        $form->update(['title' => 'ویرایش‌شده']);
        $this->seed(MarketingFormsSeeder::class);
        $this->assertSame('ویرایش‌شده', $form->fresh()->title);
    }

    public function test_consultation_booking_opens_a_crm_consultation(): void
    {
        $url = '/api/v1/public/forms/consultation-booking';
        $this->postJson($url, ['payload' => ['phone' => '0912'], '_t' => 5000])->assertStatus(422);
        $this->postJson($url, ['payload' => ['name' => 'آرش', 'phone' => '0912', 'stage' => 'نامعتبر'], '_t' => 5000])->assertStatus(422);
        $this->postJson($url, [
            'payload' => ['name' => 'آرش رضایی', 'phone' => '09120000000', 'stage' => 'فعال اما نیاز به رشد'],
            'page_uri' => '/consultation', '_hp' => '', '_t' => 5000,
        ])->assertCreated();

        $consultation = CrmConsultation::query()->firstOrFail();
        $this->assertSame('درخواست مشاوره: آرش رضایی', $consultation->title);
        $this->assertStringContainsString('وضعیت فعلی: فعال اما نیاز به رشد', $consultation->notes);
    }

    public function test_proposal_request_with_attachment_creates_a_crm_lead(): void
    {
        Storage::fake('local');
        $url = '/api/v1/public/forms/'.FormSubmissionHooks::PROPOSAL_FORM;
        $token = $this->post($url.'/upload', ['file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.token');
        $this->post($url.'/upload', ['file' => UploadedFile::fake()->create('x.exe', 1)], ['Accept' => 'application/json'])->assertStatus(422);

        $payload = ['name' => 'سارا محمدی', 'phone' => '09120000001', 'email' => 's@example.com', 'goal' => 'رشد فروش', 'details' => 'سایت فروشگاهی', 'project_types' => 'فروشگاه اینترنتی، سایر', 'attachment' => $token];
        $this->postJson($url, ['payload' => ['project_types' => 'نامعتبر'] + $payload, '_t' => 5000])->assertStatus(422);
        $this->postJson($url, ['payload' => ['attachment' => str_repeat('a', 40)] + $payload, '_t' => 5000])->assertStatus(422);
        $this->postJson($url, ['payload' => $payload, '_t' => 5000])->assertCreated();

        $lead = CrmLead::query()->firstOrFail();
        $this->assertSame('سارا', $lead->first_name);
        $this->assertSame('09120000001', $lead->mobile);
        $this->assertStringContainsString('brief.pdf', $lead->description);
    }

    public function test_cooperation_request_creates_an_hr_applicant(): void
    {
        $this->postJson('/api/v1/public/forms/'.FormSubmissionHooks::COOPERATION_FORM, ['payload' => [
            'cooperation_type' => 'همکاری پروژه‌ای / فریلنسری', 'name' => 'نیما کریمی', 'phone' => '09120000002', 'email' => 'n@example.com',
            'main_area' => 'تولید محتوا و سئو', 'experience' => 'پنج سال', 'motivation' => 'رشد',
        ], '_t' => 5000])->assertCreated();

        $applicant = HrmJobApplicant::query()->with('jobPosting')->firstOrFail();
        $this->assertSame('نیما', $applicant->first_name);
        $this->assertSame(FormSubmissionHooks::COOPERATION_POSTING, $applicant->jobPosting->title);
        $this->assertStringContainsString('نوع همکاری: همکاری پروژه‌ای / فریلنسری', $applicant->resume_notes);
        $this->assertSame(1, MarketingFormSubmission::query()->count());
    }

    public function test_honeypot_and_too_fast_submissions_are_dropped(): void
    {
        $url = '/api/v1/public/forms/consultation-booking';
        $this->postJson($url, ['payload' => ['name' => 'bot', 'phone' => '1'], '_hp' => 'x', '_t' => 5000])->assertCreated();
        $this->postJson($url, ['payload' => ['name' => 'bot', 'phone' => '1'], '_t' => 100])->assertCreated();
        $this->assertSame(0, MarketingFormSubmission::query()->count());
    }
}
