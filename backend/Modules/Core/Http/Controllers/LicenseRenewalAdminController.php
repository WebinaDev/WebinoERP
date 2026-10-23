<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\CoreLicenseRenewalPrice;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Core\Services\LicenseRenewal\LicenseRenewalService;
use Modules\Integrations\Entities\IntegrationSetting;

class LicenseRenewalAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = CoreLicenseRenewalPrice::query()->orderBy('product')->orderBy('plan')->orderBy('sort_order')->orderBy('months');
        if ($request->filled('product')) {
            $q->where('product', CoreLicenseResolver::normalizeProduct($request->string('product')));
        }

        return response()->json(['data' => $q->get()->map(fn ($p) => $p->toArray() + ['final_irr' => $p->finalIrr(), 'final_toman' => intdiv($p->finalIrr(), 10)])]);
    }

    public function store(Request $request): JsonResponse
    {
        $price = CoreLicenseRenewalPrice::query()->create($this->data($request, true));

        return response()->json(['data' => $price->fresh()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $price = CoreLicenseRenewalPrice::query()->findOrFail($id);
        $price->update($this->data($request, false));

        return response()->json(['data' => $price->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        CoreLicenseRenewalPrice::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => LicenseRenewalService::settings()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reminders' => 'required|array',
            'reminders.enabled' => 'sometimes|boolean',
            'reminders.in_app' => 'sometimes|boolean',
            'reminders.sms' => 'sometimes|boolean',
            'reminders.days' => 'sometimes|array|max:10',
            'reminders.days.*' => 'integer|min:1|max:60',
        ]);
        $merged = ['reminders' => array_merge(LicenseRenewalService::settings()['reminders'], $data['reminders'])];
        IntegrationSetting::putJson(LicenseRenewalService::SETTINGS_INTEGRATION, LicenseRenewalService::SETTINGS_KEY, $merged);

        return response()->json(['data' => LicenseRenewalService::settings()]);
    }

    public function link(Request $request, int $id, LicenseRenewalService $renewals): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);

        return response()->json(['data' => [
            'renew_url' => $renewals->renewUrl($license, $request->query('return_url')),
            'url_expires_at' => $renewals->linkExpiry()->toIso8601String(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function data(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        $data = $request->validate([
            'product' => 'sometimes|string|max:64',
            'plan' => 'nullable|string|max:64',
            'plan_name' => 'nullable|string|max:255',
            'months' => $req.'|integer|min:1|max:36',
            'price_irr' => $req.'|integer|min:1000',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'label' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);
        if (array_key_exists('product', $data) || $create) {
            $data['product'] = CoreLicenseResolver::normalizeProduct($data['product'] ?? null);
        }
        if (array_key_exists('plan', $data)) {
            $plan = strtolower(trim((string) $data['plan']));
            $data['plan'] = $plan !== '' ? $plan : null;
        }

        return $data;
    }
}
