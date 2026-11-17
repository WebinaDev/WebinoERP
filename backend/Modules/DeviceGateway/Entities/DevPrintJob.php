<?php

namespace Modules\DeviceGateway\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevPrintJob extends Model
{
    protected $table = 'dev_print_jobs';

    protected $fillable = [
        'peripheral_id',
        'job_type',
        'payload',
        'status',
        'error_message',
        'created_by',
    ];

    public function peripheral(): BelongsTo
    {
        return $this->belongsTo(DevPeripheral::class, 'peripheral_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
