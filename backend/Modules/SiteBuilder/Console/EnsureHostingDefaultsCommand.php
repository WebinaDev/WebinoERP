<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformServer;

class EnsureHostingDefaultsCommand extends Command
{
    protected $signature = 'site-builder:ensure-hosting-defaults';

    protected $description = 'Ensure platform_base_domain, provision HMAC, and localhost Platform server exist';

    public function handle(): int
    {
        $settings = CoreHostingSetting::current();
        $dirty = false;

        if (! filled($settings->platform_base_domain)) {
            $settings->platform_base_domain = 'webinaagency.ir';
            $dirty = true;
        }
        if (! filled($settings->provision_webhook_secret)) {
            $settings->provision_webhook_secret = bin2hex(random_bytes(32));
            $dirty = true;
        }
        if ($dirty) {
            $settings->save();
            $this->info('Updated core hosting defaults.');
        }

        PlatformServer::query()->firstOrCreate(
            ['name' => 'localhost'],
            [
                'ip' => '127.0.0.1',
                'port' => 22,
                'user' => 'root',
                'status' => 'ready',
                'is_localhost' => true,
                'proxy_type' => 'caddy',
                'meta' => ['managed_by' => 'site_builder'],
            ]
        );

        $this->info('Localhost platform server ready.');

        return self::SUCCESS;
    }
}
