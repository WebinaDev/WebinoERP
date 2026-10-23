<?php

namespace Modules\Crm\Http\Requests\Hub;

class DocumentVersionRequest extends HubRequest
{
    public function rules(): array
    {
        return ['file' => 'required|file|max:'.DocumentRequest::MAX_KB.'|mimes:'.DocumentRequest::MIMES, 'note' => 'nullable|string|max:500'];
    }
}
