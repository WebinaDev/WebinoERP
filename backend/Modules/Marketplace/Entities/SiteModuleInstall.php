<?php

namespace Modules\Marketplace\Entities;

use Illuminate\Database\Eloquent\Model;

class SiteModuleInstall extends Model
{
    protected $table = 'site_module_installs';

    protected $fillable = [
        'site_provision_id',
        'module_slug',
        'status',
        'version',
        'error_message',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLONING = 'cloning';

    public const STATUS_MIGRATING = 'migrating';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';
}
