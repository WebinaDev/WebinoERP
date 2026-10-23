<?php

namespace Modules\Crm\Http\Requests\Hub;

class ContractSignRequest extends HubRequest
{
    public function rules(): array
    {
        return ['signed_at' => 'nullable|date', 'file' => 'nullable|file|max:'.DocumentRequest::MAX_KB.'|mimes:'.DocumentRequest::MIMES];
    }
}
