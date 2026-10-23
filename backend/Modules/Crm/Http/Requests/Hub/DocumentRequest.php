<?php

namespace Modules\Crm\Http\Requests\Hub;

class DocumentRequest extends HubRequest
{
    public const CATEGORIES = ['national_card', 'gazette', 'enamad', 'permit', 'contract', 'proposal', 'invoice', 'brief', 'deliverable', 'other'];

    public const MAX_KB = 20480;

    public const MIMES = 'pdf,jpg,jpeg,png,webp,docx,xlsx,zip';

    protected function prepareForValidation(): void
    {
        $this->normalizeArrays(['visible_to_roles', 'tags']);
    }

    public function rules(): array
    {
        $create = $this->isMethod('post');
        $req = $create ? 'required' : 'sometimes';

        return [
            'title' => "$req|string|max:255",
            'category' => "$req|in:".implode(',', self::CATEGORIES),
            'file' => $create ? 'nullable|file|max:'.self::MAX_KB.'|mimes:'.self::MIMES : 'prohibited',
            'url' => 'nullable|url|max:1000',
            'expires_at' => 'nullable|date',
            'visible_to_roles' => 'nullable|array', 'visible_to_roles.*' => 'string|max:64',
            'tags' => 'nullable|array|max:20', 'tags.*' => 'string|max:40',
            'contract_id' => ['nullable', 'integer', $this->ownContract()],
            'project_id' => ['nullable', 'integer', $this->ownProject()],
            'review_status' => $create ? 'prohibited' : 'sometimes|in:pending,approved,rejected',
            'review_note' => 'nullable|string|max:2000',
        ];
    }
}
