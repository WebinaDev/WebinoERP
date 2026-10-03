<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmTag extends Model
{
    protected $table = 'crm_tags';

    protected $fillable = ['name', 'color'];
}
