<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGiteaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marketplace.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'host' => 'required|string|max:255',
            'org' => 'nullable|string|max:100',
            'token' => 'nullable|string|max:500',
        ];
    }
}
