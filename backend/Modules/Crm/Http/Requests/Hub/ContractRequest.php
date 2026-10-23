<?php

namespace Modules\Crm\Http\Requests\Hub;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContractRequest extends HubRequest
{
    public const STATUSES = ['draft', 'active', 'signed', 'expired', 'cancelled'];

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';
        $contractId = (int) $this->route('contractId');

        return [
            'title' => "$req|string|max:255",
            'amount' => "$req|numeric|min:0|max:999999999999999",
            'status' => 'sometimes|in:'.implode(',', self::STATUSES),
            'contract_no' => ['nullable', 'string', 'max:40', Rule::unique('prj_contracts', 'contract_no')->ignore($contractId ?: null)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'renewal_type' => 'nullable|in:none,manual,auto',
            'renewal_notice_days' => 'nullable|integer|min:0|max:365',
            'currency' => 'nullable|string|size:3',
            'tax_pct' => 'nullable|numeric|min:0|max:100',
            'deal_id' => ['nullable', 'integer', Rule::exists('crm_deals', 'id')->where('account_id', $this->accountId())],
            'project_ids' => 'nullable|array', 'project_ids.*' => ['integer', $this->ownProject()],
            'owner_id' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:10000',
            'installments' => 'nullable|array|max:120',
            'installments.*.id' => 'nullable|integer',
            'installments.*.due_date' => 'required|date',
            'installments.*.amount' => 'required|numeric|min:0',
            'installments.*.title' => 'nullable|string|max:255',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $inst = $this->input('installments');
            if (! is_array($inst) || $v->errors()->hasAny(['installments', 'amount'])) {
                return;
            }
            $amount = (float) $this->input('amount', 0);
            if ($this->isMethod('patch') && ! $this->has('amount')) {
                $amount = (float) \Modules\Projects\Entities\Contract::query()->whereKey((int) $this->route('contractId'))->value('amount');
            }
            $sum = array_sum(array_map(fn ($i) => (float) ($i['amount'] ?? 0), $inst));
            if ($sum > $amount + 0.001) {
                $v->errors()->add('installments', __('Installments total exceeds the contract amount.'));
            }
        }];
    }
}
