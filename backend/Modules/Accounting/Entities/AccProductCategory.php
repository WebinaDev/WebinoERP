<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccProductCategory extends Model
{
    protected $table = 'acc_product_categories';

    protected $fillable = ['name', 'sort_order'];
}
