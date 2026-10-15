<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;

class BuilderFormSubmission extends Model
{
    protected $table = 'builder_form_submissions';

    protected $fillable = [
        'tenant_id',
        'form_key',
        'page_uri',
        'payload',
        'ip_hash',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
