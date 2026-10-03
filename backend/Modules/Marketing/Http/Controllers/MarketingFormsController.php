<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketing\Entities\MarketingForm;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingFormsController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingForm::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:120',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'description' => 'nullable|string',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required_with:fields|string|max:64',
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.type' => 'nullable|string|in:text,email,tel,textarea,select',
            'fields.*.required' => 'nullable|boolean',
            'success_message' => 'nullable|string|max:500',
            'published' => 'nullable|boolean',
        ];
    }

    public function submissions(int $id): JsonResponse
    {
        $form = MarketingForm::query()->findOrFail($id);

        return response()->json(['data' => $form->submissions()->latest()->limit(100)->get()]);
    }
}
