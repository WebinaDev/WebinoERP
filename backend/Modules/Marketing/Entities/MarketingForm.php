<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingForm extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'fields', 'success_message', 'published',
    ];

    protected $casts = [
        'fields' => 'array',
        'published' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(MarketingFormSubmission::class, 'form_id');
    }
}
