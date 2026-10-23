<?php

namespace Modules\Crm\Http\Requests\Hub;

class ContractRenewRequest extends HubRequest
{
    public function rules(): array
    {
        return ['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'amount' => 'nullable|numeric|min:0'];
    }
}
