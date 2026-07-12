<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class CoreTaskCategory extends Model
{
    protected $table = 'core_task_categories';

    protected $fillable = ['name', 'color', 'sort_order'];
}
