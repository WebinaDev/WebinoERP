<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmAttribution extends Model
{
    protected $table = 'crm_attributions';

    protected $fillable = [
        'lead_id', 'form_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing_path',
    ];
}
