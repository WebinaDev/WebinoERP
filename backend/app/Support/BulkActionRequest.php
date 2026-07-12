<?php

namespace App\Support;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|min:1',
        ];
    }
}
