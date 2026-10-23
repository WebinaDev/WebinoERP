<?php

namespace Modules\Crm\Http\Requests\Hub;

class ProjectTaskRequest extends HubRequest
{
    public function rules(): array
    {
        return ['title' => 'required|string|max:255', 'assignee_id' => 'nullable|integer|exists:users,id', 'due_at' => 'nullable|date', 'priority' => 'nullable|in:low,medium,high,urgent'];
    }
}
