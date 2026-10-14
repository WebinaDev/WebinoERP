<?php

namespace Modules\Marketing\Http\Controllers;

use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingBlogController extends Controller
{
    use HandlesMarketingCrud;

    protected function modelClass(): string
    {
        return MarketingBlogPost::class;
    }

    protected function validationRules(bool $creating): array
    {
        return [
            'category_id' => 'nullable|exists:marketing_blog_categories,id',
            'slug' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'excerpt' => 'nullable|string',
            'body' => 'nullable|string',
            'cover_url' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:24',
            'published_at' => 'nullable|date',
            'meta' => 'nullable|array',
        ];
    }
}
