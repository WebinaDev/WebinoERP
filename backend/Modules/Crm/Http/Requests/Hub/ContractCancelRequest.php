<?php

namespace Modules\Crm\Http\Requests\Hub;

class ContractCancelRequest extends HubRequest
{
    public function rules(): array
    {
        return ['reason' => 'required|string|max:2000'];
    }
}
