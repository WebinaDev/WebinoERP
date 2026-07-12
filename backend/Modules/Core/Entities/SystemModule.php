<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemModule extends Model
{
    use HasFactory;

    protected $table = 'system_modules';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'license_key',
        'started_at',
        'expires_at',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'features' => 'array',
    ];

    /**
     * Check if module is active and license is valid
     */
    public function isLicensed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}

