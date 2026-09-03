<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformResource extends Model
{
    protected $table = 'platform_resources';

    protected $fillable = [
        'uuid',
        'environment_id',
        'server_id',
        'destination_id',
        'type',
        'name',
        'status',
        'fqdn',
        'build_pack',
        'git_repository',
        'git_branch',
        'dockerfile_location',
        'docker_compose_location',
        'docker_compose_raw',
        'docker_image',
        'database_type',
        'service_template',
        'site_type_slug',
        'license_id',
        'crm_account_id',
        'provision_id',
        'ports_exposes',
        'settings',
        'meta',
        'preview_url_template',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'meta' => 'array',
            'ports_exposes' => 'integer'
        ];
    }

    public function domains()
    {
        return $this->hasMany(PlatformDomain::class, 'resource_id');
    }

    public function envVars()
    {
        return $this->hasMany(PlatformEnvVar::class, 'resource_id');
    }

    public function volumes()
    {
        return $this->hasMany(PlatformVolume::class, 'resource_id');
    }

    public function server()
    {
        return $this->belongsTo(PlatformServer::class, 'server_id');
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
