<?php

namespace Modules\Crm\Http\Requests\Hub;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Base for hub tab requests: authorization is done per-section in the controller (403 before 422). */
abstract class HubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function accountId(): int
    {
        return (int) $this->route('id');
    }

    /** exists rule scoped to the customer in the route — another customer's id fails with 422. */
    protected function ownContract(): Exists
    {
        return Rule::exists('prj_contracts', 'id')->where('customer_account_id', $this->accountId());
    }

    protected function ownProject(): Exists
    {
        return Rule::exists('prj_projects', 'id')->where('customer_account_id', $this->accountId());
    }

    /** multipart sends arrays as JSON or CSV strings. */
    protected function normalizeArrays(array $keys): void
    {
        foreach ($keys as $k) {
            $v = $this->input($k);
            if (is_string($v)) {
                $this->merge([$k => json_decode($v, true) ?? array_values(array_filter(array_map('trim', explode(',', $v)), 'strlen'))]);
            }
        }
    }
}
