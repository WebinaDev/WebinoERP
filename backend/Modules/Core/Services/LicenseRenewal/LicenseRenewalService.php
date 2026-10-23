<?php

namespace Modules\Core\Services\LicenseRenewal;

use App\Jobs\SendSmsJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Entities\CoreLicenseRenewal;
use Modules\Core\Entities\CoreLicenseRenewalPrice;
use Modules\Core\Events\LicenseRenewed;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Crm\Events\Customer\PaymentRecorded;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteStatusService;
use Throwable;

/**
 * Self-service license renewal: price lookup, signed links, and the idempotent "paid" side effect
 * (extend expiry, paid sales invoice, reactivate license-suspended sites, timeline event, SMS).
 */
class LicenseRenewalService
{
    public const LINK_TTL_DAYS = 7;

    public const EXPIRING_DAYS = 7;

    public const SETTINGS_INTEGRATION = 'license_renewal';

    public const SETTINGS_KEY = 'settings';

    /** @return array{reminders: array{enabled:bool,days:list<int>,in_app:bool,sms:bool}} */
    public static function settings(): array
    {
        $defaults = ['reminders' => ['enabled' => true, 'days' => [7, 3, 1], 'in_app' => true, 'sms' => false]];
        try {
            $saved = IntegrationSetting::getJson(self::SETTINGS_INTEGRATION, self::SETTINGS_KEY, []);
        } catch (Throwable) {
            $saved = [];
        }
        $rem = array_merge($defaults['reminders'], is_array($saved['reminders'] ?? null) ? $saved['reminders'] : []);
        $rem['days'] = array_values(array_unique(array_map('intval', (array) $rem['days'])));
        $rem['enabled'] = (bool) $rem['enabled'];
        $rem['in_app'] = (bool) $rem['in_app'];
        $rem['sms'] = (bool) $rem['sms'];

        return ['reminders' => $rem];
    }

    public function findByDomain(string $domain): ?CoreLicense
    {
        $domain = CoreLicenseResolver::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }
        $license = CoreLicense::query()->whereRaw('LOWER(domain) = ?', [$domain])->orderByDesc('id')->first();
        if ($license) {
            return $license;
        }
        if (Schema::hasTable('webino_site_provisions')) {
            $p = WebinoSiteProvision::query()->whereNotNull('license_id')
                ->where(fn ($q) => $q->where('domain', $domain)->orWhere('subdomain', $domain))
                ->orderByDesc('id')->first();
            if ($p) {
                return CoreLicense::query()->find($p->license_id);
            }
        }

        return null;
    }

    public function plan(CoreLicense $license): ?string
    {
        $meta = is_array($license->meta) ? $license->meta : [];
        $plan = trim((string) ($meta['plan'] ?? ''));
        if ($plan === '' && Schema::hasTable('webino_site_provisions')) {
            $p = WebinoSiteProvision::query()->where('license_id', $license->id)->with('package')->latest('id')->first();
            $plan = (string) ($p?->package?->slug ?? '');
        }

        return $plan !== '' ? strtolower($plan) : null;
    }

    /** Active options for this license: plan-specific rows override catch-all rows of the same length. */
    public function options(CoreLicense $license): Collection
    {
        $product = CoreLicenseResolver::normalizeProduct($license->product ?? null);
        $plan = $this->plan($license);
        $rows = CoreLicenseRenewalPrice::query()
            ->where('is_active', true)
            ->where('product', $product)
            ->where(fn ($q) => $plan === null ? $q->whereNull('plan') : $q->whereNull('plan')->orWhere('plan', $plan))
            ->orderBy('sort_order')->orderBy('months')->get();

        return $rows->groupBy('months')
            ->map(fn (Collection $g) => $g->firstWhere('plan', '!==', null) ?? $g->first())
            ->sortBy('months')->values();
    }

    public function isRenewable(CoreLicense $license): bool
    {
        return ! in_array((string) $license->status, ['cancelled', 'revoked'], true);
    }

    public function newExpiry(CoreLicense $license, int $months): Carbon
    {
        $current = $license->expires_at ? Carbon::parse($license->expires_at) : null;
        $base = $current && $current->isFuture() ? $current->copy() : now();

        return $base->addMonthsNoOverflow($months)->endOfDay();
    }

    /** return_url is kept only if it points at the license's own site (or the platform domain). */
    public function safeReturnUrl(CoreLicense $license, ?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)) {
            return null;
        }
        $host = CoreLicenseResolver::normalizeDomain((string) ($parts['host'] ?? ''));
        $allowed = array_filter([
            CoreLicenseResolver::normalizeDomain((string) $license->domain),
            $this->platformDomain(),
            CoreLicenseResolver::normalizeDomain((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ]);
        foreach ($allowed as $root) {
            if ($host === $root || str_ends_with($host, '.'.$root)) {
                return $url;
            }
        }

        return null;
    }

    public function renewUrl(CoreLicense $license, ?string $returnUrl = null): string
    {
        return URL::temporarySignedRoute('license-renewal.page', $this->linkExpiry(), array_filter([
            'license' => $license->id,
            'return_url' => $this->safeReturnUrl($license, $returnUrl),
        ]));
    }

    public function checkoutUrl(CoreLicense $license, CoreLicenseRenewalPrice $price, ?string $returnUrl = null): string
    {
        return URL::temporarySignedRoute('license-renewal.pay', $this->linkExpiry(), array_filter([
            'license' => $license->id,
            'option' => $price->id,
            'return_url' => $this->safeReturnUrl($license, $returnUrl),
        ]));
    }

    public function linkExpiry(): Carbon
    {
        return now()->addDays(self::LINK_TTL_DAYS)->startOfMinute();
    }

    /** @return array<string, mixed> Public, PII-free info. */
    public function publicInfo(CoreLicense $license, ?string $returnUrl = null): array
    {
        $expires = $license->expires_at ? Carbon::parse($license->expires_at) : null;
        $daysLeft = $expires ? (int) floor(now()->diffInDays($expires, false)) : null;
        $site = Schema::hasTable('webino_site_provisions')
            ? WebinoSiteProvision::query()->where('license_id', $license->id)->latest('id')->first()
            : null;
        $siteStatus = $site?->site_status;
        $suspended = $siteStatus === WebinoSiteProvision::SITE_SUSPENDED;

        $status = match (true) {
            in_array((string) $license->status, ['cancelled', 'revoked'], true) => 'cancelled',
            $suspended && $site?->site_status_reason === WebinoSiteProvision::REASON_LICENSE_EXPIRED => 'suspended',
            $expires !== null && $expires->isPast() => 'expired',
            $daysLeft !== null && $daysLeft <= self::EXPIRING_DAYS => 'expiring',
            default => 'active',
        };
        $renewable = $this->isRenewable($license);
        $options = $renewable ? $this->options($license) : collect();
        $plan = $this->plan($license);
        $planName = $options->first()?->plan_name;

        $banner = ['show' => false, 'level' => null, 'message' => null];
        if ($status === 'expiring') {
            $banner = ['show' => true, 'level' => 'warning', 'message' => 'لایسنس سایت شما '.max(0, (int) $daysLeft).' روز دیگر منقضی می‌شود.'];
        } elseif (in_array($status, ['expired', 'suspended'], true)) {
            $banner = ['show' => true, 'level' => 'danger', 'message' => 'لایسنس سایت شما منقضی شده است. برای فعال‌سازی دوباره تمدید کنید.'];
        }

        return [
            'domain' => CoreLicenseResolver::normalizeDomain((string) $license->domain),
            'product' => CoreLicenseResolver::normalizeProduct($license->product ?? null),
            'plan' => ['code' => $plan, 'name' => $planName],
            'status' => $status,
            'expires_at' => $expires?->toIso8601String(),
            'days_left' => $daysLeft,
            'site_status' => $siteStatus,
            'renewable' => $renewable && $options->isNotEmpty(),
            'banner' => $banner,
            'currency' => 'IRR',
            'options' => $options->map(fn (CoreLicenseRenewalPrice $p) => $this->optionArray($license, $p) + [
                'checkout_url' => $this->checkoutUrl($license, $p, $returnUrl),
            ])->values()->all(),
            'renew_url' => $this->renewUrl($license, $returnUrl),
            'url_expires_at' => $this->linkExpiry()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function optionArray(CoreLicense $license, CoreLicenseRenewalPrice $p): array
    {
        $final = $p->finalIrr();

        return [
            'id' => $p->id,
            'months' => $p->months,
            'label' => $p->label ?: $p->months.' ماهه',
            'price_irr' => $p->price_irr,
            'discount_percent' => $p->discount_percent ? (float) $p->discount_percent : null,
            'final_irr' => $final,
            'final_toman' => intdiv($final, 10),
            'new_expires_at' => $this->newExpiry($license, $p->months)->toIso8601String(),
        ];
    }

    /** Creates (or reuses a recent pending) renewal order for this option. */
    public function order(CoreLicense $license, CoreLicenseRenewalPrice $price, ?string $returnUrl): CoreLicenseRenewal
    {
        $amount = $price->finalIrr();
        $return = $this->safeReturnUrl($license, $returnUrl);
        $existing = CoreLicenseRenewal::query()
            ->where('license_id', $license->id)->where('price_id', $price->id)
            ->where('status', 'pending')->where('amount_irr', $amount)
            ->where('created_at', '>', now()->subHours(2))->latest('id')->first();
        if ($existing) {
            if ($existing->return_url !== $return) {
                $existing->update(['return_url' => $return]);
            }

            return $existing;
        }

        return CoreLicenseRenewal::query()->create([
            'license_id' => $license->id,
            'price_id' => $price->id,
            'crm_account_id' => $this->accountId($license),
            'months' => $price->months,
            'price_irr' => $price->price_irr,
            'discount_percent' => $price->discount_percent,
            'amount_irr' => $amount,
            'status' => 'pending',
            'return_url' => $return,
        ]);
    }

    /** Payment side effect. Safe to call repeatedly: only the first call extends the license. */
    public function markPaid(int $renewalId, ?PaymentIntent $intent = null): ?CoreLicenseRenewal
    {
        $result = DB::transaction(function () use ($renewalId, $intent) {
            /** @var CoreLicenseRenewal|null $r */
            $r = CoreLicenseRenewal::query()->lockForUpdate()->find($renewalId);
            if (! $r || $r->status === 'paid') {
                return null;
            }
            $license = CoreLicense::query()->lockForUpdate()->find($r->license_id);
            if (! $license) {
                return null;
            }
            $previous = $license->expires_at ? Carbon::parse($license->expires_at) : null;
            $newExpiry = $this->newExpiry($license, $r->months);
            $license->expires_at = $newExpiry;
            if (in_array((string) $license->status, ['inactive', 'expired', 'suspended', ''], true)) {
                $license->status = 'active';
            }
            $meta = is_array($license->meta) ? $license->meta : [];
            $meta['last_renewal_id'] = $r->id;
            $meta['last_payment_id'] = $intent?->public_id;
            $meta['last_paid_at'] = now()->toIso8601String();
            $license->meta = $meta;
            $license->save();

            $invoice = $this->invoice($license, $r, $intent);
            $r->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_id' => $intent?->public_id,
                'sales_invoice_id' => $invoice?->id,
                'previous_expires_at' => $previous,
                'new_expires_at' => $newExpiry,
            ]);

            return $r->fresh();
        });

        if (! $result instanceof CoreLicenseRenewal) {
            return null;
        }
        $license = CoreLicense::query()->find($result->license_id);
        CoreLicenseResolver::forgetCheckCache($license?->domain, $license?->product ?? null);
        CoreLicenseMetaNormalizer::forgetCheckCache($license?->domain, null);

        if ($license) {
            $this->reactivate($license);
            $this->afterPaid($license, $result, $intent);
        }

        return $result;
    }

    public function markFailed(int $renewalId): void
    {
        CoreLicenseRenewal::query()->where('id', $renewalId)->where('status', 'pending')->update(['status' => 'failed']);
    }

    /** Same rule as site-builder:enforce-license-status: lift only license_expired suspensions. */
    public function reactivate(CoreLicense $license): int
    {
        if (! Schema::hasTable('webino_site_provisions')) {
            return 0;
        }
        if ($license->expires_at && Carbon::parse($license->expires_at)->lte(now())) {
            return 0;
        }
        $count = 0;
        $sites = WebinoSiteProvision::query()
            ->where('license_id', $license->id)
            ->where('status', WebinoSiteProvision::STATUS_READY)
            ->where('site_status', WebinoSiteProvision::SITE_SUSPENDED)
            ->where('site_status_reason', WebinoSiteProvision::REASON_LICENSE_EXPIRED)
            ->get();
        foreach ($sites as $site) {
            try {
                app(SiteStatusService::class)->set($site, WebinoSiteProvision::SITE_ACTIVE, null, null, null, 'license_renewal');
                $count++;
            } catch (Throwable $e) {
                // The hourly site-builder:enforce-license-status run retries.
                Log::warning('license_renewal.reactivate_failed', ['site' => $site->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $count;
    }

    public function accountId(CoreLicense $license): ?int
    {
        $id = (int) ($license->getAttribute('crm_account_id') ?? 0);
        if ($id <= 0 && Schema::hasTable('webino_site_provisions')) {
            $id = (int) (WebinoSiteProvision::query()->where('license_id', $license->id)->whereNotNull('crm_account_id')->value('crm_account_id') ?? 0);
        }

        return $id > 0 ? $id : null;
    }

    public function smsConfigured(): ?array
    {
        try {
            $settings = IntegrationSetting::getJson('sms', 'settings', []);
        } catch (Throwable) {
            return null;
        }
        $provider = (string) ($settings['provider'] ?? '');

        return in_array($provider, ['melipayamak', 'parsgreen'], true) ? $settings : null;
    }

    public function sendSms(?int $accountId, string $text): bool
    {
        $settings = $this->smsConfigured();
        if ($settings === null || ! $accountId || ! Schema::hasTable('crm_accounts')) {
            return false;
        }
        $row = DB::table('crm_accounts')->where('id', $accountId)->first(['mobile', 'phone']);
        $to = trim((string) ($row->mobile ?? '') ?: (string) ($row->phone ?? ''));
        if ($to === '') {
            return false;
        }
        SendSmsJob::dispatch((string) $settings['provider'], $to, $text, $settings);

        return true;
    }

    private function invoice(CoreLicense $license, CoreLicenseRenewal $r, ?PaymentIntent $intent): ?SalesInvoice
    {
        if (! Schema::hasTable('sales_invoices')) {
            return null;
        }
        $accountId = $r->crm_account_id ?: $this->accountId($license);
        $name = $accountId && Schema::hasTable('crm_accounts')
            ? (string) (DB::table('crm_accounts')->where('id', $accountId)->value('name') ?? '')
            : '';
        $title = 'تمدید لایسنس '.$license->domain.' — '.$r->months.' ماه';
        $discount = max(0, $r->price_irr - $r->amount_irr);

        $invoice = new SalesInvoice;
        $invoice->forceFill(array_intersect_key([
            'number' => 'LR-'.now()->format('ymd').'-'.str_pad((string) $r->id, 5, '0', STR_PAD_LEFT),
            'customer_name' => $name !== '' ? $name : (string) $license->domain,
            'customer_id' => $accountId,
            'total' => $r->amount_irr,
            'subtotal' => $r->price_irr,
            'discount' => $discount,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'payment_method' => $intent ? 'cash:'.$intent->gateway : 'license_renewal',
            'items' => [['title' => $title, 'qty' => 1, 'unit_price' => $r->price_irr, 'discount' => $discount, 'total' => $r->amount_irr]],
            'notes' => 'license_renewal:'.$r->id.($intent ? ' payment:'.$intent->public_id : ''),
        ], array_flip(Schema::getColumnListing('sales_invoices'))));
        $invoice->save();

        return $invoice;
    }

    private function afterPaid(CoreLicense $license, CoreLicenseRenewal $r, ?PaymentIntent $intent): void
    {
        $accountId = $r->crm_account_id ?: $this->accountId($license);
        try {
            event(new LicenseRenewed($license->id, $r->id, $accountId, (string) $r->new_expires_at?->toIso8601String()));
            if ($accountId) {
                event(new PaymentRecorded($accountId, $r->sales_invoice_id, [
                    'kind' => 'license_renewal',
                    'license_id' => $license->id,
                    'renewal_id' => $r->id,
                    'months' => $r->months,
                    'amount' => $r->amount_irr,
                    'payment_id' => $intent?->public_id,
                    'new_expires_at' => $r->new_expires_at?->toIso8601String(),
                ], null, now()->toIso8601String(), 'تمدید لایسنس '.$license->domain));
            }
        } catch (Throwable $e) {
            Log::warning('license_renewal.event_failed', ['renewal' => $r->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
        }
        try {
            $this->sendSms($accountId, 'لایسنس '.$license->domain.' تا '.$r->new_expires_at?->format('Y-m-d').' تمدید شد. مبلغ '.number_format(intdiv($r->amount_irr, 10)).' تومان پرداخت شد.');
        } catch (Throwable $e) {
            Log::warning('license_renewal.sms_failed', ['renewal' => $r->id]);
        }
    }

    private function platformDomain(): ?string
    {
        try {
            $d = (string) (CoreHostingSetting::query()->value('platform_base_domain') ?? '');
        } catch (Throwable) {
            $d = '';
        }

        return $d !== '' ? CoreLicenseResolver::normalizeDomain($d) : null;
    }
}
