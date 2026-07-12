<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.leads.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic' => 'sometimes|string|max:255',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'company' => 'nullable|string|max:150',
            'job_title' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'sometimes|string|max:20',
            'phone' => 'nullable|string|max:20',
            'source_id' => 'nullable|exists:crm_sources,id',
            'status_id' => 'sometimes|exists:crm_statuses,id',
            'industry' => 'nullable|string|max:50',
            'rating' => 'nullable|integer|min:1|max:5',
            'lead_score' => 'nullable|integer',
            'assigned_to' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'address_json' => 'nullable|array',
        ];
    }
}
