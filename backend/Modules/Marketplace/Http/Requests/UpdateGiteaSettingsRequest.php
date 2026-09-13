<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGiteaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'nullable|in:gitea,github,gitlab',
            'host' => 'nullable|string|max:255',
            'base_url' => 'nullable|string|max:255',
            'org' => 'nullable|string|max:100',
            'token' => 'nullable|string|max:500',
            'platform_source_id' => 'nullable|exists:platform_sources,id',
            'ip_override' => 'nullable|string|max:100',
            'ip_scheme' => 'nullable|in:auto,http,https',
            // CRM aliases
            'gitea_base_url' => 'nullable|string|max:255',
            'gitea_org' => 'nullable|string|max:100',
            'gitea_api_token' => 'nullable|string|max:500',
            'gitea_ip_override' => 'nullable|string|max:100',
            'gitea_ip_scheme' => 'nullable|in:auto,http,https',
        ];
    }
}
