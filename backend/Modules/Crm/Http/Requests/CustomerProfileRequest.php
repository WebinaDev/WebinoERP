<?php

namespace Modules\Crm\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Support\IranianIds;

/**
 * Full Customer 360 edit form (PATCH /customers/{id}/hub/profile).
 * Authorization (profile / profile_sensitive) is enforced in the controller.
 */
class CustomerProfileRequest extends FormRequest
{
    /** Fields that need crm.hub.profile.sensitive in addition to profile manage. */
    public const SENSITIVE = ['credit_limit', 'iban', 'kyc_status', 'kyc_note', 'allowed_ips', 'payment_terms_days', 'default_discount_pct', 'price_list_id'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['national_id', 'economic_code', 'registration_no', 'postal_code', 'mobile', 'phone'] as $k) {
            if ($this->filled($k) && is_string($this->input($k))) {
                $merge[$k] = IranianIds::normalize($this->input($k));
            }
        }
        if ($this->filled('iban') && is_string($this->input('iban'))) {
            $merge['iban'] = IranianIds::normalizeIban($this->input('iban'));
        }
        if ($this->filled('email') && is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');
        $kind = $this->input('customer_kind');

        return [
            // identity
            'name' => 'sometimes|required|string|max:255',
            'brand_name' => 'nullable|string|max:200',
            'customer_kind' => 'nullable|in:individual,legal,partner,vip',
            'hub_status' => 'nullable|in:active,dormant,debtor,blocked,prospect',
            'national_id' => ['nullable', 'string', 'max:30', function (string $attr, $value, Closure $fail) use ($kind) {
                // individual → کد ملی (10), legal → شناسه ملی (11); otherwise accept either.
                $ok = match ($kind) {
                    'individual' => IranianIds::isNationalCode($value),
                    'legal' => IranianIds::isLegalId($value),
                    default => IranianIds::isNationalCode($value) || IranianIds::isLegalId($value),
                };
                if (! $ok) {
                    $fail($kind === 'legal' ? 'شناسه ملی معتبر نیست.' : ($kind === 'individual' ? 'کد ملی معتبر نیست.' : 'کد ملی / شناسه ملی معتبر نیست.'));
                }
            }],
            'economic_code' => ['nullable', 'string', 'regex:/^\d{11,14}$/'],
            'registration_no' => ['nullable', 'string', 'regex:/^\d{1,12}$/'],
            'industry' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:1000',
            // contact
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{4,15}$/'],
            'mobile' => ['nullable', 'string', 'regex:/^(\+98|0098|98|0)?9\d{9}$/'],
            'email' => 'nullable|email|max:150',
            'socials' => 'nullable|array',
            'socials.*' => 'nullable|string|max:255',
            // legacy single address (structured ones live in /hub/addresses)
            'postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'address_line' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            // classification
            'tags' => 'nullable|array|max:50',
            'tags.*' => 'string|max:50',
            'source' => 'nullable|string|max:40',
            'segment' => 'nullable|string|max:40',
            'custom_fields' => 'nullable|array|max:100',
            'custom_fields.*' => 'nullable',
            // relations
            'account_manager_id' => 'nullable|integer|exists:users,id',
            'owner_id' => 'nullable|integer|exists:users,id',
            'referrer_account_id' => ['nullable', 'integer', 'exists:crm_accounts,id', Rule::notIn([$id])],
            'partner_account_id' => ['nullable', 'integer', 'exists:crm_accounts,id', Rule::notIn([$id])],
            'parent_id' => ['nullable', 'integer', 'exists:crm_accounts,id', Rule::notIn([$id])],
            // preferences
            'preferred_language' => 'nullable|string|max:10',
            'preferred_channel' => 'nullable|in:phone,sms,email,whatsapp',
            'timezone' => 'nullable|timezone',
            'marketing_sms_opt_out' => 'nullable|boolean',
            'currency' => 'nullable|string|size:3',
            // sensitive (finance / KYC / security)
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms_days' => 'nullable|integer|min:0|max:3650',
            'default_discount_pct' => 'nullable|numeric|min:0|max:100',
            'price_list_id' => 'nullable|integer|min:1',
            'iban' => ['nullable', 'string', function (string $attr, $value, Closure $fail) {
                if (! IranianIds::isIban($value)) {
                    $fail('شماره شبا معتبر نیست.');
                }
            }],
            'kyc_status' => 'nullable|in:none,pending,verified,rejected',
            'kyc_note' => 'nullable|string|max:2000',
            'allowed_ips' => 'nullable|array|max:100',
            'allowed_ips.*' => 'ip',
        ];
    }

    /** @return list<string> */
    public function sensitiveKeys(): array
    {
        return array_values(array_intersect(array_keys($this->validated()), self::SENSITIVE));
    }
}
