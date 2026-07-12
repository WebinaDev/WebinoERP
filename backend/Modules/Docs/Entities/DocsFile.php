<?php

namespace Modules\Docs\Entities;

use Illuminate\Database\Eloquent\Model;

class DocsFile extends Model
{
    protected $table = 'docs_files';

    protected $fillable = ['name', 'path', 'mime_type', 'size', 'uploaded_by', 'folder_id', 'disk', 'version', 'share_token'];

    protected $casts = ['size' => 'integer'];
}
