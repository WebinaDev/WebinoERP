<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrjForm extends Model
{
    protected $table = 'prj_forms';

    protected $fillable = [
        'slug',
        'title',
        'fields',
        'success_message',
        'notify_emails',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'notify_emails' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PrjFormSubmission::class, 'form_id');
    }
}
