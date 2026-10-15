<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesProductQuestion extends Model
{
    protected $table = 'sales_product_questions';

    protected $fillable = ['product_id', 'user_id', 'body', 'answer', 'status'];

    protected $casts = [];
}
