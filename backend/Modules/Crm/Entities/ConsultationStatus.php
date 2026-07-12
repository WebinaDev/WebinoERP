<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class ConsultationStatus extends Model
{
    protected $table = 'crm_consultation_statuses';

    protected $fillable = ['name', 'color', 'sort_order'];
}
