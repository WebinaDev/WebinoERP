<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';

    protected $fillable = ['number', 'customer_name', 'total', 'status', 'issue_date', 'created_by'];

    protected $casts = [
        'total' => 'decimal:2',
        'issue_date' => 'date',
    ];
}
