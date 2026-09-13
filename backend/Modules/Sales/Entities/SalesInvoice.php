<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';

    protected $fillable = [
        'number',
        'invoice_number',
        'customer_name',
        'customer_id',
        'project_id',
        'project_title',
        'total',
        'subtotal',
        'discount',
        'status',
        'issue_date',
        'payment_method',
        'items',
        'notes',
        'created_by',
    ];

    protected $appends = ['final_total', 'date_display'];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'issue_date' => 'date',
        'items' => 'array',
        'customer_id' => 'integer',
        'project_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesInvoice $invoice) {
            if ($invoice->invoice_number && ! $invoice->number) {
                $invoice->number = $invoice->invoice_number;
            }
            if ($invoice->number && ! $invoice->invoice_number) {
                $invoice->invoice_number = $invoice->number;
            }
        });
    }

    public function getFinalTotalAttribute(): float
    {
        return (float) ($this->total ?? 0);
    }

    public function getDateDisplayAttribute(): ?string
    {
        return $this->issue_date?->format('Y-m-d');
    }
}
