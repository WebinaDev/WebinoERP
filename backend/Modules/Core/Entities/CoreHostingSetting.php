<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton-style hosting / infra configuration row (first row is authoritative).
 *
 * @property string|null $public_crm_url
 * @property string|null $git_provider
 * @property string|null $git_base_url
 * @property string|null $git_pat
 * @property string|null $portainer_url
 * @property string|null $portainer_api_token
 * @property string|null $portainer_tls_fingerprint
 * @property int|null $portainer_endpoint_id
 * @property string|null $git_webhook_secret
 * @property string|null $webinoserver_panel_url
 * @property string|null $webinoserver_api_token
 * @property string|null $platform_base_domain
 * @property string|null $default_product_channel
 * @property string|null $provision_webhook_secret
 */
class CoreHostingSetting extends Model
{
    protected $table = 'core_hosting_settings';

    protected $fillable = [
        'public_crm_url',
        'git_provider',
        'git_base_url',
        'git_pat',
        'portainer_url',
        'portainer_api_token',
        'portainer_tls_fingerprint',
        'portainer_endpoint_id',
        'git_webhook_secret',
        'webinoserver_panel_url',
        'webinoserver_api_token',
        'platform_base_domain',
        'default_product_channel',
        'provision_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'git_pat' => 'encrypted',
            'portainer_api_token' => 'encrypted',
            'git_webhook_secret' => 'encrypted',
            'webinoserver_api_token' => 'encrypted',
            'provision_webhook_secret' => 'encrypted',
            'portainer_endpoint_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row instanceof self) {
            return $row;
        }

        /** @var self */
        return static::query()->create([]);
    }
}
