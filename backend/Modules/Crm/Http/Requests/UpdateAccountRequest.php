<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:50',
            'owner_id' => 'nullable|exists:users,id',
            'website' => 'nullable|url|max:255',
            'industry' => 'nullable|string|max:100',
        ];
    }
}
