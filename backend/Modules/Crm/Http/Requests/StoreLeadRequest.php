<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
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
            'topic' => 'required|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company' => 'nullable|string|max:150',
            'job_title' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'required|string|max:20',
            'phone' => 'nullable|string|max:20',
            'source_id' => 'nullable|exists:crm_sources,id',
            'status_id' => 'required|exists:crm_statuses,id',
            'industry' => 'nullable|string|max:50',
            'rating' => 'nullable|integer|min:1|max:5',
            'lead_score' => 'nullable|integer',
            'assigned_to' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'address_json' => 'nullable|array',
        ];
    }
}
