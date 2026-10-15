<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesProductReview extends Model
{
    protected $table = 'sales_product_reviews';

    protected $fillable = ['product_id', 'user_id', 'author_name', 'rating', 'body', 'status', 'likes', 'reports'];

    protected $casts = ['rating' => 'integer', 'likes' => 'integer', 'reports' => 'integer'];
}
