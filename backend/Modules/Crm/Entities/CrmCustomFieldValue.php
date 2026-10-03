<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCustomFieldValue extends Model
{
    protected $table = 'crm_custom_field_values';

    protected $fillable = ['field_id', 'entity_type', 'entity_id', 'value'];
}
