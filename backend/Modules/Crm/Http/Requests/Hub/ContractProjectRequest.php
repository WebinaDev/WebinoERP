<?php

namespace Modules\Crm\Http\Requests\Hub;

class ContractProjectRequest extends HubRequest
{
    public function rules(): array
    {
        return ['project_id' => ['required', 'integer', $this->ownProject()]];
    }
}
