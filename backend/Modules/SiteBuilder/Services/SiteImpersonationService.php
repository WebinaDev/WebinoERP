<?php

namespace Modules\SiteBuilder\Services;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformResource;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Exceptions\SiteImpersonationException;
use Modules\SiteBuilder\Support\ImpersonationNextPath;
use Modules\SiteBuilder\Support\ImpersonationPassport;
use Throwable;

/**
 * Passwordless entry from ERP site control into a customer dashboard.
 * The tenant session is the site admin; the passport only lets staff hop
 * among sites they can already manage. Passwords are never read or stored.
 */
class SiteImpersonationService
{
    public function __construct(
        private readonly SiteProvisionOrchestrator $orchestrator,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    /**
     * @return array{url: string, expires_in: int}
     */
    public function enter(User $staff, WebinoSiteProvision $site, ?string $next, ?string $ip): array
    {
        $this->assertStaff($staff);
        $this->assertEnterable($site);
        $passport = ImpersonationPassport::issue((int) $staff->id);
        $target = ImpersonationNextPath::normalize($next);

        try {
            $url = $this->requestTenantLogin($site, $staff, $passport, $target);
        } catch (Throwable $e) {
            ImpersonationPassport::revoke($passport);
            if ($e instanceof SiteImpersonationException) {
                throw $e;
            }

            throw new SiteImpersonationException(
                $e->getMessage() !== '' ? $e->getMessage() : 'ساخت لینک ورود پنل ناموفق بود.',
                422,
                $e,
            );
        }

        $this->audit->log((int) $staff->id, 'impersonation.enter', $site, $this->auditPayload($staff, $site, $target, $ip));
        Log::info('site_impersonation.enter', [
            'staff_id' => $staff->id,
            'provision_id' => $site->id,
            'domain' => $site->domain,
        ]);

        return ['url' => $url, 'expires_in' => 300];
    }

    /**
     * @return array{url: string, expires_in: int}
     */
    public function exchange(string $passport, int $provisionId, ?string $next, ?string $ip): array
    {
        return $this->withPassport($passport, function (array $claims) use ($passport, $provisionId, $next, $ip): array {
            $staff = $this->staffFromClaims($claims);
            $site = WebinoSiteProvision::query()->with('crmAccount')->find($provisionId);
            if (! $site instanceof WebinoSiteProvision) {
                throw new SiteImpersonationException('سایت پیدا نشد.', 404);
            }
            $this->assertEnterable($site);
            $target = ImpersonationNextPath::normalize($next);
            $fresh = ImpersonationPassport::issue((int) $staff->id);

            try {
                $url = $this->requestTenantLogin($site, $staff, $fresh, $target);
            } catch (Throwable $e) {
                ImpersonationPassport::revoke($fresh);
                if ($e instanceof SiteImpersonationException) {
                    throw $e;
                }

                throw new SiteImpersonationException(
                    $e->getMessage() !== '' ? $e->getMessage() : 'انتقال به سایت دیگر ناموفق بود.',
                    422,
                    $e,
                );
            }

            ImpersonationPassport::revoke($passport);
            $this->audit->log((int) $staff->id, 'impersonation.switch', $site, $this->auditPayload($staff, $site, $target, $ip));
            Log::info('site_impersonation.switch', [
                'staff_id' => $staff->id,
                'provision_id' => $site->id,
                'domain' => $site->domain,
            ]);

            return ['url' => $url, 'expires_in' => 300];
        });
    }

    /**
     * @return array{return_url: string}
     */
    public function exit(string $passport, ?int $provisionId, ?string $ip): array
    {
        return $this->withPassport($passport, function (array $claims) use ($passport, $provisionId, $ip): array {
            $staff = User::query()->find($claims['sid']);
            $site = $provisionId
                ? WebinoSiteProvision::query()->with('crmAccount')->find($provisionId)
                : null;
            ImpersonationPassport::revoke($passport);

            if ($site instanceof WebinoSiteProvision && $staff instanceof User) {
                $this->audit->log(
                    (int) $staff->id,
                    'impersonation.exit',
                    $site,
                    $this->auditPayload($staff, $site, null, $ip),
                );
            }

            Log::info('site_impersonation.exit', [
                'staff_id' => $claims['sid'],
                'provision_id' => $provisionId,
            ]);

            $return = $site instanceof WebinoSiteProvision
                ? $this->returnUrl($site)
                : $this->erpBase().'/dashboard/admin/platform/sites';

            return ['return_url' => $return];
        });
    }

    /**
     * @param  callable(array{sid: int, exp: int, jti: string}): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withPassport(string $passport, callable $callback): array
    {
        $claims = ImpersonationPassport::parse($passport);
        $lock = Cache::lock('site_impersonation_lock:'.$claims['jti'], 30);
        $acquired = false;

        try {
            $lock->block(5);
            $acquired = true;
            $claims = ImpersonationPassport::parse($passport);

            return $callback($claims);
        } catch (LockTimeoutException) {
            throw new SiteImpersonationException('درخواست دیگری در حال انجام است. دوباره تلاش کنید.', 429);
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }

    private function requestTenantLogin(WebinoSiteProvision $site, User $staff, string $passport, string $next): string
    {
        $result = $this->orchestrator->callTenantApi($site, 'provision/panel-login', [
            'next' => $next,
            'impersonation' => $this->tenantPayload($staff, $site, $passport),
        ]);
        $payload = is_array($result['data'] ?? null) ? $result['data'] : $result;
        $url = $payload['login_url'] ?? $payload['one_shot_url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new SiteImpersonationException('سایت لینک ورود برنگرداند.', 422);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(User $staff, WebinoSiteProvision $site, string $passport): array
    {
        $exp = time() + ImpersonationPassport::TTL_SECONDS;

        return [
            'staff_id' => (int) $staff->id,
            'staff_name' => $this->staffName($staff),
            'customer_name' => $this->customerName($site),
            'site_name' => $this->siteName($site),
            'domain' => $this->host((string) $site->domain),
            'provision_id' => (int) $site->id,
            'return_url' => $this->returnUrl($site),
            'expires_at' => gmdate('c', $exp),
            'passport' => $passport,
            'sites' => $this->sites((int) $site->id),
        ];
    }

    /**
     * @return list<array{provision_id: int, domain: string, name: string, customer_name: string, current: bool}>
     */
    private function sites(int $currentId): array
    {
        $rows = WebinoSiteProvision::query()
            ->with('crmAccount')
            ->whereIn('status', [
                WebinoSiteProvision::STATUS_READY,
                WebinoSiteProvision::STATUS_SSL_PENDING,
                WebinoSiteProvision::STATUS_FAILED,
            ])
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $sites = [];
        foreach ($rows as $row) {
            if (! $row instanceof WebinoSiteProvision) {
                continue;
            }
            $domain = $this->host((string) $row->domain);
            if ($domain === '') {
                continue;
            }
            $sites[] = [
                'provision_id' => (int) $row->id,
                'domain' => $domain,
                'name' => $this->siteName($row),
                'customer_name' => $this->customerName($row),
                'current' => (int) $row->id === $currentId,
            ];
        }

        return $sites;
    }

    private function assertStaff(User $staff): void
    {
        if ($staff->is_active === false) {
            throw new SiteImpersonationException('حساب کارکنان غیرفعال است.', 403);
        }

        try {
            if ($staff->hasRole('system_manager') || $staff->can('site_builder.provision.manage')) {
                return;
            }
        } catch (Throwable) {
            // Permission catalog may be missing; fail closed below.
        }

        throw new SiteImpersonationException('دسترسی کافی برای ورود به داشبورد مشتری ندارید.', 403);
    }

    /**
     * @param  array{sid: int, exp: int, jti: string}  $claims
     */
    private function staffFromClaims(array $claims): User
    {
        $staff = User::query()->find($claims['sid']);
        if (! $staff instanceof User) {
            throw new SiteImpersonationException('نشست جانشینی نامعتبر یا منقضی شده است.', 401);
        }
        $this->assertStaff($staff);

        return $staff;
    }

    private function assertEnterable(WebinoSiteProvision $site): void
    {
        if ($this->host((string) $site->domain) === '') {
            throw new SiteImpersonationException('دامنه سایت مشخص نیست.', 422);
        }

        $allowed = [
            WebinoSiteProvision::STATUS_READY,
            WebinoSiteProvision::STATUS_SSL_PENDING,
            WebinoSiteProvision::STATUS_FAILED,
        ];
        if (in_array($site->status, $allowed, true)) {
            return;
        }

        $blocked = [
            WebinoSiteProvision::STATUS_DRAFT,
            WebinoSiteProvision::STATUS_PENDING,
            WebinoSiteProvision::STATUS_PROVISIONING,
            WebinoSiteProvision::STATUS_CANCELLED,
        ];
        if (in_array($site->status, $blocked, true)) {
            throw new SiteImpersonationException(
                'سایت در این وضعیت قابل ورود نیست: '.$site->status,
                422,
            );
        }

        $live = PlatformResource::query()
            ->where('provision_id', $site->id)
            ->where('status', '!=', 'destroyed')
            ->exists();
        if (! $live) {
            throw new SiteImpersonationException(
                'سایت در این وضعیت قابل ورود نیست: '.$site->status,
                422,
            );
        }
    }

    private function staffName(User $staff): string
    {
        $name = trim((string) $staff->name);

        return $name !== '' ? mb_substr($name, 0, 120) : mb_substr((string) $staff->email, 0, 120);
    }

    private function siteName(WebinoSiteProvision $site): string
    {
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];
        $name = trim((string) ($wizard['site_name'] ?? ''));
        if ($name === '') {
            $name = $this->host((string) $site->domain);
        }

        return mb_substr($name, 0, 160);
    }

    private function customerName(WebinoSiteProvision $site): string
    {
        $account = trim((string) ($site->crmAccount?->name ?? ''));
        if ($account !== '') {
            return mb_substr($account, 0, 160);
        }
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];
        $admin = trim((string) ($wizard['admin_name'] ?? ''));
        if ($admin !== '') {
            return mb_substr($admin, 0, 160);
        }

        return $this->siteName($site);
    }

    private function host(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $host = strtolower(trim(explode('/', $domain)[0] ?? ''));

        return rtrim($host, '.');
    }

    private function returnUrl(WebinoSiteProvision $site): string
    {
        return $this->erpBase().'/dashboard/admin/platform/sites/'.$site->id;
    }

    private function erpBase(): string
    {
        $configured = '';
        try {
            $configured = rtrim((string) (CoreHostingSetting::current()->public_crm_url ?: ''), '/');
        } catch (Throwable) {
            $configured = '';
        }
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(User $staff, WebinoSiteProvision $site, ?string $next, ?string $ip): array
    {
        return [
            'staff_id' => (int) $staff->id,
            'staff_name' => $this->staffName($staff),
            'customer_name' => $this->customerName($site),
            'domain' => $this->host((string) $site->domain),
            'next' => $next,
            'ip' => $ip ? mb_substr($ip, 0, 64) : null,
        ];
    }
}
