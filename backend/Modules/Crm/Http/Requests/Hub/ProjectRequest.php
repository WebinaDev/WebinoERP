<?php

namespace Modules\Crm\Http\Requests\Hub;

use Illuminate\Validation\Rule;

class ProjectRequest extends HubRequest
{
    public const STATUSES = ['draft', 'active', 'on_hold', 'completed', 'cancelled'];

    protected function prepareForValidation(): void
    {
        if ($this->input('status') === 'planning') { // contract name → Projects status machine name
            $this->merge(['status' => 'draft']);
        }
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => "$req|string|max:255",
            'description' => 'nullable|string|max:10000',
            'status' => 'sometimes|in:'.implode(',', self::STATUSES),
            'manager_user_id' => 'nullable|integer|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'contract_id' => ['nullable', 'integer', $this->ownContract()],
            'template_id' => ['nullable', 'integer', Rule::exists('prj_projects', 'id')->where('is_template', true)],
            'progress_pct' => 'nullable|integer|min:0|max:100',
        ];
    }
}
