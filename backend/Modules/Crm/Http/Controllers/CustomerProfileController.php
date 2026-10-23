<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmAccountAddress;
use Modules\Crm\Events\CustomerHubUpdated;
use Modules\Crm\Http\Requests\CustomerProfileRequest;
use Modules\Crm\Services\Hub\CustomerHubAccess;
use Modules\Crm\Services\Hub\CustomerHubService;
use Modules\Crm\Services\Hub\CustomerMergeService;
use Modules\Crm\Support\IranianIds;

/** Customer 360 phase 1: full edit form, addresses, logo upload and merge. */
class CustomerProfileController extends Controller
{
    public function __construct(private CustomerHubAccess $access, private CustomerHubService $hub) {}

    private function account(int $id): CrmAccount
    {
        return CrmAccount::query()->findOrFail($id);
    }

    private function gateView(Request $r): void
    {
        abort_unless($this->access->canView($r->user(), 'identity'), 403, __('Forbidden'));
    }

    private function gateManage(Request $r, string $action): void
    {
        abort_unless($this->access->canManage($r->user(), $action), 403, __('Forbidden'));
    }

    private function audit(Request $r, CrmAccount $a, string $action, array $changes = []): void
    {
        DB::table('crm_customer_audit')->insert([
            'account_id' => $a->id, 'user_id' => $r->user()?->id, 'action' => $action,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'ip' => $r->ip(), 'created_at' => now(),
        ]);
        MutationAudit::record($r->user()?->id, 'crm', 'customer_hub.'.$action, CrmAccount::class, $a->id, $changes);
        try {
            event(new CustomerHubUpdated($a->id, $action, $r->user()?->id));
        } catch (\Throwable) {
            // broadcasting is best-effort
        }
    }

    /** Form payload: every editable field, sensitive ones only for holders of crm.hub.profile.sensitive. */
    private function payload(Request $r, CrmAccount $a): array
    {
        $sensitive = $this->access->canManage($r->user(), 'profile_sensitive');
        $a->loadMissing(['accountManager:id,name', 'addresses']);
        $out = [
            'id' => $a->id, 'name' => $a->name, 'account_code' => $a->account_code, 'type' => $a->type,
            'brand_name' => $a->brand_name, 'customer_kind' => $a->customer_kind, 'hub_status' => $a->hub_status,
            'national_id' => $a->national_id, 'economic_code' => $a->economic_code, 'registration_no' => $a->registration_no,
            'industry' => $a->industry, 'description' => $a->description, 'website' => $a->website, 'logo_url' => $a->logo_url,
            'phone' => $a->phone, 'mobile' => $a->mobile, 'email' => $a->email, 'socials' => $a->socials ?? (object) [],
            'postal_code' => $a->postal_code, 'address_line' => $a->address_line, 'city' => $a->city, 'province' => $a->province, 'country' => $a->country,
            'tags' => $a->tags ?? [], 'source' => $a->source, 'segment' => $a->segment, 'custom_fields' => $a->custom_fields ?? (object) [],
            'owner_id' => $a->owner_id, 'account_manager_id' => $a->account_manager_id, 'account_manager' => $a->accountManager?->only(['id', 'name']),
            'parent_id' => $a->parent_id, 'referrer_account_id' => $a->referrer_account_id, 'partner_account_id' => $a->partner_account_id,
            'preferred_language' => $a->preferred_language, 'preferred_channel' => $a->preferred_channel, 'timezone' => $a->timezone,
            'marketing_sms_opt_out' => (bool) $a->marketing_sms_opt_out, 'currency' => $a->currency ?: 'IRR',
            'merged_into_id' => $a->merged_into_id, 'last_activity_at' => $a->last_activity_at, 'lifetime_value' => $a->lifetime_value,
            'iban_masked' => IranianIds::maskIban($a->iban),
            'addresses' => $a->addresses->sortByDesc('is_default')->values(),
            'can_edit' => $this->access->canManage($r->user(), 'profile'),
            'can_edit_sensitive' => $sensitive,
            'can_merge' => $this->access->canManage($r->user(), 'merge'),
        ];
        if ($sensitive) {
            $out += [
                'credit_limit' => $a->credit_limit, 'payment_terms_days' => $a->payment_terms_days,
                'default_discount_pct' => $a->default_discount_pct, 'price_list_id' => $a->price_list_id,
                'kyc_status' => $a->kyc_status ?: 'none', 'kyc_note' => $a->kyc_note, 'kyc_verified_at' => $a->kyc_verified_at,
                'allowed_ips' => $a->allowed_ips ?? [],
            ];
        }

        // Legacy identity shape (profile / contacts / kyc / preferences) kept for the current HubContext dialog.
        return $out + $this->hub->identity($a);
    }

    public function show(Request $r, int $id): JsonResponse
    {
        $this->gateView($r);

        return response()->json(['data' => $this->payload($r, $this->account($id))]);
    }

    public function update(CustomerProfileRequest $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        abort_if($a->merged_into_id !== null, 409, 'This customer was merged into #'.$a->merged_into_id);
        $data = $r->validated();
        if ($r->sensitiveKeys() !== []) {
            $this->gateManage($r, 'profile_sensitive');
        }
        if (($data['kyc_status'] ?? null) === 'verified' && $a->kyc_status !== 'verified') {
            $data['kyc_verified_at'] = now();
        }
        if (array_key_exists('economic_code', $data) && blank($a->tax_id)) {
            $data['tax_id'] = $data['economic_code'];
        }

        $before = [];
        foreach (array_keys($data) as $k) {
            $before[$k] = $a->{$k};
        }
        $a->fill($data)->save();

        // Diff without leaking the IBAN into audit logs.
        $diff = ['before' => [], 'after' => []];
        foreach ($data as $k => $v) {
            if ($before[$k] == $a->{$k} && $k !== 'iban') {
                continue;
            }
            if ($k === 'iban') {
                if ($before[$k] === $a->iban) {
                    continue;
                }
                $diff['before'][$k] = IranianIds::maskIban($before[$k]);
                $diff['after'][$k] = IranianIds::maskIban($a->iban);

                continue;
            }
            $diff['before'][$k] = $before[$k] instanceof \DateTimeInterface ? $before[$k]->format(DATE_ATOM) : $before[$k];
            $diff['after'][$k] = $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v;
        }
        if ($diff['after'] !== []) {
            $this->audit($r, $a, $r->sensitiveKeys() ? 'profile.sensitive_update' : 'profile.update', $diff);
        }

        return response()->json(['data' => $this->payload($r, $a->fresh()), 'changes' => array_keys($diff['after'])]);
    }

    // ───────────── addresses ─────────────

    private function addressRules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'type' => "$req|in:billing,shipping,office,other",
            'title' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'line' => "$req|string|max:2000",
            'postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ];
    }

    private function normalizeAddress(Request $r): void
    {
        if (is_string($r->input('postal_code'))) {
            $r->merge(['postal_code' => IranianIds::normalize($r->input('postal_code')) ?: null]);
        }
    }

    private function onlyDefault(CrmAccountAddress $addr): void
    {
        if ($addr->is_default) {
            CrmAccountAddress::query()->where('account_id', $addr->account_id)->where('type', $addr->type)
                ->whereKeyNot($addr->id)->update(['is_default' => false]);
        }
    }

    public function addresses(Request $r, int $id): JsonResponse
    {
        $this->gateView($r);

        return response()->json(['data' => $this->account($id)->addresses()->orderByDesc('is_default')->orderBy('id')->get()]);
    }

    public function storeAddress(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        $this->normalizeAddress($r);
        $data = $r->validate($this->addressRules(false));
        if (! $a->addresses()->where('type', $data['type'])->exists()) {
            $data['is_default'] = true; // first address of a type becomes default
        }
        $addr = $a->addresses()->create($data);
        $this->onlyDefault($addr);
        $this->audit($r, $a, 'address.create', ['address_id' => $addr->id, 'type' => $addr->type]);

        return response()->json(['data' => $addr->fresh()], 201);
    }

    public function updateAddress(Request $r, int $id, int $addressId): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        $addr = $a->addresses()->findOrFail($addressId);
        $this->normalizeAddress($r);
        $data = $r->validate($this->addressRules(true));
        $addr->fill($data)->save();
        $this->onlyDefault($addr);
        $this->audit($r, $a, 'address.update', ['address_id' => $addr->id, 'fields' => array_keys($data)]);

        return response()->json(['data' => $addr->fresh()]);
    }

    public function deleteAddress(Request $r, int $id, int $addressId): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        $addr = $a->addresses()->findOrFail($addressId);
        $addr->delete();
        if ($addr->is_default && ($next = $a->addresses()->where('type', $addr->type)->orderBy('id')->first())) {
            $next->update(['is_default' => true]);
        }
        $this->audit($r, $a, 'address.delete', ['address_id' => $addressId, 'type' => $addr->type]);

        return response()->json(null, 204);
    }

    // ───────────── logo ─────────────

    public function uploadLogo(Request $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'profile');
        $a = $this->account($id);
        $r->validate(['logo' => 'required|file|max:2048|mimes:png,jpg,jpeg,webp,svg']);
        $up = $r->file('logo');
        $ext = strtolower($up->guessExtension() ?: $up->getClientOriginalExtension() ?: 'png');
        $path = $up->storeAs('crm/customer-logos/'.$a->id, Str::uuid()->toString().'.'.$ext, 'public');
        $old = $a->logo_url;
        $a->forceFill(['logo_url' => Storage::disk('public')->url($path)])->save();
        if ($old && str_contains($old, '/crm/customer-logos/'.$a->id.'/')) {
            Storage::disk('public')->delete('crm/customer-logos/'.$a->id.'/'.basename($old));
        }
        $this->audit($r, $a, 'profile.logo', ['logo_url' => $a->logo_url]);

        return response()->json(['data' => ['logo_url' => $a->logo_url]]);
    }

    // ───────────── merge ─────────────

    /** POST /customers/{id}/hub/merge {source_id}: folds source_id into {id}. */
    public function merge(Request $r, int $id, CustomerMergeService $merge): JsonResponse
    {
        $this->gateManage($r, 'merge');
        $data = $r->validate(['source_id' => 'required|integer|exists:crm_accounts,id']);
        $res = $merge->merge($id, (int) $data['source_id'], $r->user()->id);
        try {
            event(new CustomerHubUpdated($id, 'customer.merge', $r->user()->id));
            event(new CustomerHubUpdated((int) $data['source_id'], 'customer.merged_away', $r->user()->id));
        } catch (\Throwable) {
        }

        return response()->json(['data' => ['account' => $this->payload($r, $res['account']), 'moved' => $res['moved']]]);
    }
}
