<?php

namespace Modules\DeviceGateway\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevPeripheral extends Model
{
    protected $table = 'dev_peripherals';

    protected $fillable = [
        'gateway_id',
        'device_type',
        'name',
        'model',
        'connection_type',
        'port_identifier',
        'config',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(DevGateway::class, 'gateway_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(DevPrintJob::class, 'peripheral_id');
    }
}
