<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmProductRule extends Model
{
    protected $table = 'crm_product_rules';

    protected $fillable = ['product_id', 'related_product_id', 'kind', 'min_qty'];
}
