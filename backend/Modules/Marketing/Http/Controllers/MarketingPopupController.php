<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Modules\Marketing\Entities\MarketingPopup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MarketingPopupController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:draft,active,paused'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'content' => ['nullable', 'array'],
            'content.title' => ['nullable', 'string', 'max:200'],
            'content.body' => ['nullable', 'string', 'max:3000'],
            'content.image_url' => ['nullable', 'string', 'max:1024'],
            'content.cta_label' => ['nullable', 'string', 'max:80'],
            'content.cta_url' => ['nullable', 'string', 'max:1024'],
            'content.coupon_code' => ['nullable', 'string', 'max:64'],
            'content.collect' => ['nullable', 'in:none,phone,email'],
            'content.position' => ['nullable', 'in:center,bottom,corner'],
            'trigger' => ['required', 'in:load,delay,scroll,exit,idle'],
            'trigger_value' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'targeting' => ['nullable', 'array'],
            'targeting.include' => ['nullable', 'array', 'max:50'],
            'targeting.include.*' => ['string', 'max:255'],
            'targeting.exclude' => ['nullable', 'array', 'max:50'],
            'targeting.exclude.*' => ['string', 'max:255'],
            'targeting.devices' => ['nullable', 'array'],
            'targeting.devices.*' => ['in:desktop,mobile'],
            'targeting.audience' => ['nullable', 'in:all,guest,logged_in'],
            'targeting.new_visitors_only' => ['nullable', 'boolean'],
            'display' => ['nullable', 'array'],
            'display.frequency' => ['nullable', 'in:always,session,once,days'],
            'display.days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'display.max_views' => ['nullable', 'integer', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    private function forget(): void
    {
        Cache::forget('marketing:popups:public');
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => MarketingPopup::query()->orderByDesc('priority')->orderByDesc('id')->get()]);
    }

    public function show(Request $request, int $popup): JsonResponse
    {
        return response()->json(['data' => MarketingPopup::query()->findOrFail($popup)]);
    }

    public function store(Request $request): JsonResponse
    {
        $row = MarketingPopup::query()->create($request->validate($this->rules()));
        $this->forget();

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $popup): JsonResponse
    {
        $row = MarketingPopup::query()->findOrFail($popup);
        $row->update($request->validate($this->rules()));
        $this->forget();

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(Request $request, int $popup): JsonResponse
    {
        MarketingPopup::query()->findOrFail($popup)->delete();
        $this->forget();

        return response()->json(['ok' => true]);
    }

    /** Active, in-window popups for the storefront; path/device/audience/frequency are evaluated client-side. */
    public function publicIndex(Request $request): JsonResponse
    {
        $rows = Cache::remember('marketing:popups:public', 60, fn () => MarketingPopup::query()
            ->where('status', 'active')
            ->orderByDesc('priority')->get(['id', 'content', 'trigger', 'trigger_value', 'targeting', 'display', 'starts_at', 'ends_at', 'updated_at'])
            ->toArray());
        $now = now();
        $out = array_values(array_filter($rows, static fn ($r) => (empty($r['starts_at']) || $now->gte($r['starts_at'])) && (empty($r['ends_at']) || $now->lte($r['ends_at']))));

        return response()->json(['data' => $out]);
    }

    public function leads(int $popup): JsonResponse
    {
        MarketingPopup::query()->findOrFail($popup);

        return response()->json(['data' => DB::table('marketing_popup_leads')->where('popup_id', $popup)->orderByDesc('id')->limit(500)->get()]);
    }

    public function event(Request $request, int $popup): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:view,click,close,lead'], 'value' => ['nullable', 'string', 'max:190']]);
        $row = MarketingPopup::query()->where('status', 'active')->findOrFail($popup);
        $col = ['view' => 'views', 'click' => 'clicks', 'close' => 'closes', 'lead' => 'leads'][$data['type']];
        $row->increment($col);
        if ($data['type'] === 'lead' && ! empty($data['value'])) {
            $v = trim($data['value']);
            $isEmail = str_contains($v, '@');
            DB::table('marketing_popup_leads')->insertOrIgnore([
                'popup_id' => $row->id, 'kind' => $isEmail ? 'email' : 'phone', 'value' => $v,
                'ip_hash' => hash('sha256', (string) $request->ip()), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
