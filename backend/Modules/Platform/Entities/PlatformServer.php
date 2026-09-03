<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformServer extends Model
{
    protected $table = 'platform_servers';

    protected $fillable = [
        'uuid',
        'name',
        'ip',
        'port',
        'user',
        'ssh_key_id',
        'status',
        'is_localhost',
        'proxy_type',
        'meta',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_localhost' => 'boolean',
            'last_seen_at' => 'datetime',
            'port' => 'integer'
        ];
    }

    public function sshKey()
    {
        return $this->belongsTo(PlatformSshKey::class, 'ssh_key_id');
    }

    public function destinations()
    {
        return $this->hasMany(PlatformDestination::class, 'server_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (in_array('uuid', $model->getFillable(), true) && empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
