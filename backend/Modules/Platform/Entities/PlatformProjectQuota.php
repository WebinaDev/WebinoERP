<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProjectQuota extends Model
{
    protected $table = 'platform_project_quotas';

    protected $fillable = [
        'project_id',
        'cpu_percent_soft',
        'cpu_percent_hard',
        'ram_mb_soft',
        'ram_mb_hard',
        'disk_mb_soft',
        'disk_mb_hard',
        'inode_soft',
        'inode_hard',
        'bandwidth_gb_month_soft',
        'bandwidth_gb_month_hard',
        'enforce_action',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent_soft' => 'float',
            'cpu_percent_hard' => 'float',
            'ram_mb_soft' => 'float',
            'ram_mb_hard' => 'float',
            'disk_mb_soft' => 'float',
            'disk_mb_hard' => 'float',
            'inode_soft' => 'integer',
            'inode_hard' => 'integer',
            'bandwidth_gb_month_soft' => 'float',
            'bandwidth_gb_month_hard' => 'float',
            'is_enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlatformProject::class, 'project_id');
    }
}
