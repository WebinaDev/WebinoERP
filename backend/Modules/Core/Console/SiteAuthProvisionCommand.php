<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Core\Services\SiteAuth;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;

/**
 * Idempotent: find every known site (Basalam connections + active licenses) without a Site-Auth
 * secret and (with --nudge) ask the site to enroll now. The secret is still generated only inside
 * POST /site-auth/enroll and delivered only to the site's own receive_url (proof of domain control);
 * this command never creates, prints or sends a secret itself.
 */
class SiteAuthProvisionCommand extends Command
{
    protected $signature = 'site-auth:provision {--nudge : POST {site}/wp-json/webino-dashboard/v1/site-auth/enroll-now for missing sites} {--domain=* : limit to these domains}';

    protected $description = 'List sites missing a Site-Auth secret and optionally nudge them to enroll (no secrets printed)';

    public const NUDGE_PATH = '/wp-json/webino-dashboard/v1/site-auth/enroll-now';

    public function handle(): int
    {
        $domains = collect();
        foreach (MarketplaceBasalamConnection::query()->pluck('site_url') as $url) {
            $domains->push((string) parse_url((string) $url, PHP_URL_HOST));
        }
        foreach (CoreLicense::query()->whereNotNull('domain')->where('status', 'active')->pluck('domain') as $d) {
            $domains->push((string) $d);
        }
        $only = array_map(fn ($d) => CoreLicenseResolver::normalizeDomain((string) $d), (array) $this->option('domain'));
        $domains = $domains->map(fn ($d) => CoreLicenseResolver::normalizeDomain($d))
            ->filter(fn ($d) => $d !== '' && str_contains($d, '.') && ($only === [] || in_array($d, $only, true)))
            ->unique()->sort()->values();

        $missing = 0;
        foreach ($domains as $d) {
            if (SiteAuth::isEnrolled($d)) {
                $this->line("{$d}\tenrolled");

                continue;
            }
            $missing++;
            $state = 'missing';
            if ($this->option('nudge')) {
                try {
                    $res = Http::timeout(15)->connectTimeout(8)->acceptJson()->withOptions(['allow_redirects' => false])
                        ->post('https://'.$d.self::NUDGE_PATH, ['domain' => $d]);
                    $state .= ' nudge='.self::classifyNudge($res->status(), (array) $res->json());
                } catch (\Throwable $e) {
                    $state .= ' nudge=error';
                }
            }
            $this->line("{$d}\t{$state}");
        }
        $this->line('total: '.$domains->count().' missing: '.$missing.' mode: '.SiteAuth::mode()
            .' basalam_require_signed: '.(config('integrations.basalam.require_signed') ? 'on' : 'off'));

        return self::SUCCESS;
    }

    /**
     * Interpret the plugin's enroll-now reply (WebinaDashboard PR #6, 670d20d):
     * 200 {ok,enrolled:true} → enrolled; 200 {ok,enrolled:false,scheduled:true} → scheduled (success,
     * enrollment runs via wp-cron); 429 {code:rate_limited} → retry_later; anything else → failed(<status>).
     */
    public static function classifyNudge(int $status, array $body): string
    {
        if ($status === 429) {
            return 'retry_later';
        }
        if ($status >= 200 && $status < 300 && ($body['ok'] ?? false) === true) {
            if (($body['enrolled'] ?? false) === true) {
                return 'enrolled';
            }
            if (($body['scheduled'] ?? false) === true) {
                return 'scheduled';
            }

            return 'ok';
        }

        return 'failed('.$status.')';
    }
}
