<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmSegment extends Model
{
    protected $table = 'crm_segments';

    protected $fillable = ['name', 'entity', 'filters', 'created_by'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
