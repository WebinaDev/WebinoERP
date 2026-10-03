<?php

namespace Modules\Marketing\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Entities\MarketingFormSubmission;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Http\Controllers\Controller;

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
            $rules[$key] = $rule.'|string|max:5000'.($type === 'email' ? '|email' : '');
        }
        $data = $request->validate($rules + ['payload' => 'required|array']);
        $row = MarketingFormSubmission::query()->create([
            'form_id' => $form->id,
            'payload' => $data['payload'],
            'locale' => substr((string) $request->header('Accept-Language', ''), 0, 8) ?: null,
        ]);

        return response()->json([
            'data' => ['id' => $row->id, 'message' => $form->success_message ?: 'دریافت شد'],
        ], 201);
    }
}
