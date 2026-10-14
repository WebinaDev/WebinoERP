<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Services\SiteAuth;
use Modules\Integrations\Entities\IntegrationSetting;

class SiteAuthCommand extends Command
{
    protected $signature = 'site-auth {action : status|reset} {domain? : domain for reset}';

    protected $description = 'Per-site Edge HMAC auth: list enrolled domains and mode, or reset a domain secret';

    public function handle(): int
    {
        if ($this->argument('action') === 'reset') {
            $domain = (string) $this->argument('domain');
            if ($domain === '') {
                $this->error('domain required');

                return self::FAILURE;
            }
            SiteAuth::forget($domain);
            $this->info("reset {$domain}; the site re-enrolls on its next sync");

            return self::SUCCESS;
        }

        $this->line('mode: '.SiteAuth::mode());
        $rows = IntegrationSetting::query()->where('integration', SiteAuth::SETTING_GROUP)
            ->where('key', 'like', 'enrolled_at:%')->orderBy('key')->get(['key', 'value']);
        foreach ($rows as $row) {
            $this->line(substr((string) $row->key, strlen('enrolled_at:'))."\t".$row->value);
        }
        $this->line('enrolled: '.$rows->count());

        return self::SUCCESS;
    }
}
