<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $table = 'seo_redirects';

    protected $fillable = ['tenant_id', 'from_path', 'to_path', 'status_code', 'enabled', 'note'];

    protected $casts = ['enabled' => 'boolean', 'status_code' => 'integer'];
}
