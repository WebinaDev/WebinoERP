<?php

namespace Modules\FieldService\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Entities\AccProduct;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;

class FsInstallBase extends Model
{
    protected $table = 'fs_install_bases';

    protected $fillable = [
        'serial_number',
        'device_model',
        'account_id',
        'contact_id',
        'product_id',
        'install_date',
        'warranty_start',
        'warranty_end',
        'status',
        'installation_address',
        'specs',
    ];

    protected $casts = [
        'install_date' => 'date',
        'warranty_start' => 'date',
        'warranty_end' => 'date',
        'specs' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(FsServiceOrder::class, 'install_base_id');
    }

    public function isUnderWarranty(): bool
    {
        if (! $this->warranty_end) {
            return false;
        }

        return now()->toDateString() <= $this->warranty_end->toDateString();
    }
}
