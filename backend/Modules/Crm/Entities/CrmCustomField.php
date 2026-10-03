<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCustomField extends Model
{
    protected $table = 'crm_custom_fields';

    protected $fillable = ['entity', 'key', 'label', 'type', 'options', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }
}
