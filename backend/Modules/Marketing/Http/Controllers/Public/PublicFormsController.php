<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingFormSubmission;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Services\Builder\FormWidgetActions;
use Modules\Marketing\Services\FormSubmissionHooks;
use Modules\Marketing\Services\FormUploads;

class PublicFormsController extends Controller
{
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
            if ($type === 'file') {
                $rules[$key][] = 'regex:/^[A-Za-z0-9]{40}$/';
                $rules[$key][] = fn (string $attr, mixed $value, \Closure $fail) => FormUploads::valid((string) $value) ? null : $fail('فایل پیوست نامعتبر است.');
            }
            if ($type === 'checkbox' && ! empty($field['options']) && is_array($field['options'])) {
                $allowed = array_map('strval', $field['options']);
                $rules[$key][] = fn (string $attr, mixed $value, \Closure $fail) => array_diff(array_map('trim', explode('،', (string) $value)), $allowed) ? $fail('گزینه نامعتبر است.') : null;
            }
            if ($type === 'select' && ! empty($field['options']) && is_array($field['options'])) {
                $rules[$key][] = Rule::in(array_values(array_map('strval', $field['options'])));
            }
        }
        // Builder form anti-spam (ported from Dashboard): hidden honeypot filled or submitted too fast.
        if (filled($request->input('_hp')) || ($request->has('_t') && (int) $request->input('_t') < 1500)) {
            return response()->json(['data' => ['id' => null, 'message' => $form->success_message ?: 'دریافت شد']], 201);
        }
        $data = $request->validate($rules + ['payload' => 'required|array', 'page_uri' => 'nullable|string|max:255', 'widget_id' => 'nullable|string|max:64']);
        // Widget-level captcha / CRM action / webhook come from the published builder document, never the request.
        $widget = FormWidgetActions::props($data['widget_id'] ?? null, $form->slug);
        if ($widget && $widget['captcha'] && $request->input('_captcha') !== '1') {
            return response()->json(['message' => 'لطفاً تأیید کنید که ربات نیستید.', 'errors' => ['_captcha' => ['required']]], 422);
        }
        $row = MarketingFormSubmission::query()->create([
            'form_id' => $form->id,
            'payload' => $data['payload'],
            'locale' => substr((string) $request->header('Accept-Language', ''), 0, 8) ?: null,
        ]);

        FormSubmissionHooks::dispatch($form, $data['payload'], $data['page_uri'] ?? null);
        if ($widget) {
            FormWidgetActions::run($widget, $form->slug, $data['payload'], FormSubmissionHooks::notes($form, $data['payload'], $data['page_uri'] ?? null));
        }

        return response()->json([
            'data' => ['id' => $row->id, 'message' => $form->success_message ?: 'دریافت شد'],
        ], 201);
    }

    /** Private attachment for a published form that has a file field; returns an opaque token. */
    public function upload(Request $request, string $slug): JsonResponse
    {
        $form = MarketingForm::query()->where('slug', $slug)->where('published', true)->firstOrFail();
        $hasFile = collect($form->fields ?? [])->contains(fn ($f) => is_array($f) && ($f['type'] ?? '') === 'file');
        abort_unless($hasFile, 422, 'این فرم فایل پیوست ندارد.');
        $request->validate(['file' => 'required|file|mimes:'.FormUploads::MIMES.'|max:'.FormUploads::MAX_KB]);

        return response()->json(['data' => ['token' => FormUploads::store($request->file('file'), $form->slug)]], 201);
    }
}
