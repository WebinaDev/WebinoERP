<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmDocumentTemplate extends Model
{
    protected $table = 'hrm_document_templates';

    protected $fillable = ['slug', 'name', 'html', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
