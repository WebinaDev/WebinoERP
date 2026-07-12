<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Projects\Entities\PrjForm;
use Modules\Projects\Entities\PrjFormSubmission;

class FormController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PrjForm::query()->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:120|regex:/^[a-z0-9\-]+$/|unique:prj_forms,slug',
            'title' => 'required|string|max:255',
            'fields' => 'required|array',
            'success_message' => 'nullable|string|max:500',
            'notify_emails' => 'nullable|array',
            'notify_emails.*' => 'email',
            'is_active' => 'boolean',
        ]);
        $form = PrjForm::query()->create($data + ['is_active' => $data['is_active'] ?? true]);

        return response()->json(['data' => $form], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => PrjForm::query()->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $form = PrjForm::query()->findOrFail($id);
        $data = $request->validate([
            'slug' => 'sometimes|string|max:120|regex:/^[a-z0-9\-]+$/|unique:prj_forms,slug,'.$form->id,
            'title' => 'sometimes|string|max:255',
            'fields' => 'sometimes|array',
            'success_message' => 'nullable|string|max:500',
            'notify_emails' => 'nullable|array',
            'notify_emails.*' => 'email',
            'is_active' => 'boolean',
        ]);
        $form->update($data);

        return response()->json(['data' => $form->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        PrjForm::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    /**
     * Public: POST /api/v1/forms/{slug}/submit
     */
    public function submit(Request $request, string $slug): JsonResponse
    {
        if ($request->filled('website')) {
            return response()->json(['message' => 'Invalid'], 422);
        }

        $form = PrjForm::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $secret = config('services.recaptcha.secret');
        if ($secret && $request->filled('recaptcha_token')) {
            $res = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $request->input('recaptcha_token'),
                'remoteip' => $request->ip(),
            ])->json();
            if (empty($res['success']) || (float) ($res['score'] ?? 0) < 0.5) {
                return response()->json(['message' => 'reCAPTCHA failed'], 422);
            }
        }

        $payload = $request->except(['recaptcha_token', 'website']);
        $submission = PrjFormSubmission::query()->create([
            'form_id' => $form->id,
            'data' => $payload,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000),
        ]);

        $lead = $this->maybeCreateLeadFromForm($form, $payload);
        if ($lead) {
            $submission->update(['converted_lead_id' => $lead->id]);
        }

        return response()->json([
            'data' => [
                'ok' => true,
                'message' => $form->success_message ?? 'Thank you.',
                'submission_id' => $submission->id,
                'lead_id' => $lead?->id,
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function maybeCreateLeadFromForm(PrjForm $form, array $payload): ?CrmLead
    {
        $defaultStatusId = CrmStatus::query()->orderBy('id')->value('id');
        if (! $defaultStatusId) {
            return null;
        }

        $map = collect($form->fields)->where('type', 'lead_map')->first();
        if (! is_array($map) || empty($map['columns'])) {
            $email = $payload['email'] ?? $payload['Email'] ?? null;
            if (! $email) {
                return null;
            }

            return CrmLead::query()->create([
                'topic' => $form->title,
                'email' => $email,
                'first_name' => $payload['first_name'] ?? $payload['firstName'] ?? '-',
                'last_name' => $payload['last_name'] ?? $payload['lastName'] ?? '-',
                'mobile' => $payload['mobile'] ?? $payload['phone'] ?? '-',
                'status_id' => $defaultStatusId,
                'description' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_by' => null,
            ]);
        }

        $attrs = [];
        foreach ($map['columns'] as $field => $leadCol) {
            if (isset($payload[$field])) {
                $attrs[$leadCol] = $payload[$field];
            }
        }
        if (empty($attrs['email']) && isset($payload['email'])) {
            $attrs['email'] = $payload['email'];
        }
        if (empty($attrs)) {
            return null;
        }

        return CrmLead::query()->create(array_merge([
            'topic' => $attrs['topic'] ?? $form->title,
            'first_name' => $attrs['first_name'] ?? '-',
            'last_name' => $attrs['last_name'] ?? '-',
            'mobile' => $attrs['mobile'] ?? '-',
            'status_id' => $defaultStatusId,
            'description' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ], $attrs));
    }
}

