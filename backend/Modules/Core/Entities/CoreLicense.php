<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreLicense extends Model
{
    protected $table = 'core_licenses';

    protected $fillable = [
        'license_key', 'project_name', 'domain', 'logo_url', 'status', 'start_date',
        'expires_at', 'max_users', 'meta', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'start_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
