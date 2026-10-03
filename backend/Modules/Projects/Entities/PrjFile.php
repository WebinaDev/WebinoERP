<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class PrjFile extends Model
{
    protected $table = 'prj_files';

    protected $fillable = [
        'project_id', 'task_id', 'family_id', 'version', 'name', 'disk', 'path',
        'size_bytes', 'shared_with_client', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['shared_with_client' => 'boolean'];
    }
}
