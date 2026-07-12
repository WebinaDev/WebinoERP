<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccUnit extends Model
{
    protected $table = 'acc_units';

    protected $fillable = ['name', 'symbol'];
}
