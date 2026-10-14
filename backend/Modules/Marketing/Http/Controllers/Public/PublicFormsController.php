<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingFormSubmission;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Http\Controllers\Controller;

class PublicFormsController extends Controller
{
    /** Forms whose submissions also open a CRM consultation (CRM ← مشاوره‌ها). */
    private const CONSULTATION_FORMS = ['consultation', 'consultation-booking'];

    public function menu(string $location): JsonResponse
    {
        $menu = MarketingMenu::query()->where('location', $location)->where('published', true)->first();
        if (! $menu) {
            return response()->json(['data' => ['location' => $location, 'items' => []]]);
        }
        $items = $menu->items()->where('published', true)->whereNull('parent_id')->with(['children' => fn ($q) => $q->where('published', true)])->get();

        return response()->json(['data' => [
            'location' => $menu->location,
            'name' => $menu->name,
            'items' => $items,
        ]]);
    }

    public function show(string $slug): JsonResponse
    {
        $form = MarketingForm::query()->where('slug', $slug)->where('published', true)->firstOrFail();

        return response()->json(['data' => $form->only(['slug', 'title', 'description', 'fields', 'success_message'])]);
    }

    public function submit(Request $request, string $slug): JsonResponse
    {
        $form = MarketingForm::query()->where('slug', $slug)->where('published', true)->firstOrFail();
        $rules = [];
        foreach ($form->fields ?? [] as $field) {
            if (! is_array($field) || empty($field['name'])) {
                continue;
            }
            $key = 'payload.'.$field['name'];
            $rule = ! empty($field['required']) ? 'required' : 'nullable';
            $type = $field['type'] ?? 'text';
            $rules[$key] = [$rule, 'string', 'max:5000'];
            if ($type === 'email') {
                $rules[$key][] = 'email';
            }
            if ($type === 'select' && ! empty($field['options']) && is_array($field['options'])) {
                $rules[$key][] = Rule::in(array_values(array_map('strval', $field['options'])));
            }
        }
        // Builder form anti-spam (ported from Dashboard): hidden honeypot filled or submitted too fast.
        if (filled($request->input('_hp')) || ($request->has('_t') && (int) $request->input('_t') < 1500)) {
            return response()->json(['data' => ['id' => null, 'message' => $form->success_message ?: 'دریافت شد']], 201);
        }
        $data = $request->validate($rules + ['payload' => 'required|array', 'page_uri' => 'nullable|string|max:255']);
        $row = MarketingFormSubmission::query()->create([
            'form_id' => $form->id,
            'payload' => $data['payload'],
            'locale' => substr((string) $request->header('Accept-Language', ''), 0, 8) ?: null,
        ]);

        if (in_array($form->slug, self::CONSULTATION_FORMS, true)) {
            $this->openConsultation($form, $data['payload'], $data['page_uri'] ?? null);
        }

        return response()->json([
            'data' => ['id' => $row->id, 'message' => $form->success_message ?: 'دریافت شد'],
        ], 201);
    }

    private function openConsultation(MarketingForm $form, array $payload, ?string $page): void
    {
        $labels = collect($form->fields ?? [])->filter(fn ($f) => is_array($f) && ! empty($f['name']))
            ->mapWithKeys(fn ($f) => [$f['name'] => $f['label'] ?? $f['name']]);
        $notes = collect($payload)
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v, $k) => ($labels[$k] ?? $k).': '.$v)
            ->push('منبع: '.($page ?: 'سایت عمومی').' (فرم '.$form->slug.')')
            ->implode("\n");
        $name = trim((string) ($payload['name'] ?? ''));

        CrmConsultation::query()->create([
            'title' => $name !== '' ? 'درخواست مشاوره: '.mb_substr($name, 0, 200) : 'درخواست مشاوره از سایت',
            'status' => 'new',
            'notes' => $notes,
        ]);
    }
}
