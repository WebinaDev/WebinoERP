<?php

namespace Modules\FieldService\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Entities\CrmAccount;
use Modules\Projects\Entities\PrjTicket;
use Modules\Sales\Entities\SalesInvoice;

class FsServiceOrder extends Model
{
    protected $table = 'fs_service_orders';

    protected $fillable = [
        'order_number',
        'install_base_id',
        'account_id',
        'ticket_id',
        'technician_id',
        'priority',
        'status',
        'scheduled_at',
        'dispatched_at',
        'started_at',
        'completed_at',
        'sla_response_due',
        'sla_resolution_due',
        'sla_response_breached',
        'sla_resolution_breached',
        'problem_description',
        'resolution_notes',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'sales_invoice_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sla_response_due' => 'datetime',
        'sla_resolution_due' => 'datetime',
        'sla_response_breached' => 'boolean',
        'sla_resolution_breached' => 'boolean',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function installBase(): BelongsTo
    {
        return $this->belongsTo(FsInstallBase::class, 'install_base_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PrjTicket::class, 'ticket_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(FsTechnician::class, 'technician_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(FsSparePartUsed::class, 'service_order_id');
    }
}
