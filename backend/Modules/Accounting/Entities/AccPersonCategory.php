<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccPersonCategory extends Model
{
    protected $table = 'acc_person_categories';

    protected $fillable = ['name', 'sort_order'];
}
