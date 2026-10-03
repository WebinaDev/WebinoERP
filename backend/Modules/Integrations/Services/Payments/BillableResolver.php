<?php

namespace Modules\Integrations\Services\Payments;

use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Projects\Entities\ProInvoice;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class BillableResolver
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    public function resolve(array $input, ?string $tenantDomain = null): array
    {
        $type = (string) ($input['payable_type'] ?? '');
        $id = (string) ($input['payable_id'] ?? '');
        $domain = $this->domain($input['domain'] ?? null);
        if ($tenantDomain !== null) {
            $domain = $this->domain($tenantDomain);
        }

        $bill = match ($type) {
            'sales_invoice' => $this->salesInvoice($id),
            'accounting_invoice' => $this->accountingInvoice($id),
            'project_invoice' => $this->projectInvoice($id),
            'marketplace_order' => $this->marketplaceOrder($id),
            'sms_credit' => $this->smsPackage($id, $domain),
            'sms_order' => $this->smsOrder($id),
            'license' => $this->license($id),
            'subscription' => $this->subscription($id, $domain),
            'wallet_topup' => $this->wallet($id, $domain, $input['amount'] ?? null),
            'generic' => $this->generic($id, $input['amount'] ?? null, $input['description'] ?? null, $domain),
            default => throw new PaymentException('نوع صورتحساب پشتیبانی نمی‌شود.', 'unknown_payable'),
        };

        if ($tenantDomain !== null) {
            $billDomain = $this->domain($bill['domain'] ?? null);
            if ($billDomain === null || $billDomain !== $domain) {
                throw new PaymentException('این صورتحساب به سایت شما تعلق ندارد.', 'tenant_mismatch', 403);
            }
        }

        if ($bill['base_amount'] < 1000) {
            throw new PaymentException('مبلغ صورتحساب باید حداقل ۱۰۰۰ ریال باشد.', 'amount');
        }

        return $bill;
    }

    /**
     * @return array{bills:list<array<string,mixed>>,offers:list<array<string,mixed>>}
     */
    public function outstanding(?string $domain): array
    {
        $domain = $this->domain($domain);
        $bills = [];
        $offers = [];

        if (Schema::hasTable('modirpayamak_orders')) {
            $orders = ModirPayamakOrder::query()->where('status', 'pending')->latest();
            if ($domain) {
                $orders->where('domain', $domain);
            }
            foreach ($orders->limit(50)->get() as $order) {
                $bills[] = $this->row('sms_order', (string) $order->id, 'شارژ پیامک', (int) round((float) $order->amount), (string) $order->domain, 'pending');
            }
        }

        if (Schema::hasTable('modirpayamak_packages')) {
            foreach (ModirPayamakPackage::query()->where('is_active', true)->orderBy('sort_order')->limit(50)->get() as $package) {
                $offers[] = $this->row('sms_credit', (string) $package->id, 'بسته پیامک: '.$package->name, (int) round((float) $package->amount), $domain, 'offer');
            }
        }

        if (Schema::hasTable('marketplace_orders')) {
            $query = MarketplaceOrder::query()->where('status', 'pending')->latest();
            if ($domain && Schema::hasTable('webino_site_provisions')) {
                $siteIds = WebinoSiteProvision::query()->where('domain', $domain)->pluck('id');
                $query->whereIn('site_provision_id', $siteIds);
            }
            foreach ($query->limit(50)->get() as $order) {
                $bills[] = $this->row('marketplace_order', (string) $order->id, 'سفارش بازارچه '.$order->order_number, (int) round((float) $order->total), $domain, (string) $order->status);
            }
        }

        if (Schema::hasTable('sales_invoices') && $domain === null) {
            foreach (SalesInvoice::query()->whereNotIn('status', ['paid', 'cancelled'])->latest()->limit(30)->get() as $invoice) {
                $bills[] = $this->row('sales_invoice', (string) $invoice->id, 'فاکتور فروش '.$invoice->number, (int) round((float) $invoice->total), null, (string) $invoice->status);
            }
        }

        if (Schema::hasTable('acc_invoices') && $domain === null) {
            foreach (AccountingInvoice::query()->whereNotIn('status', ['paid', 'cancelled'])->latest()->limit(30)->get() as $invoice) {
                $bills[] = $this->row('accounting_invoice', (string) $invoice->id, 'فاکتور حسابداری '.$invoice->number, (int) round((float) $invoice->total), null, (string) $invoice->status);
            }
        }

        if (Schema::hasTable('prj_pro_invoices') && $domain === null) {
            foreach (ProInvoice::query()->whereNotIn('status', ['paid', 'cancelled'])->latest()->limit(30)->get() as $invoice) {
                $bills[] = $this->row('project_invoice', (string) $invoice->id, 'پیش‌فاکتور '.$invoice->number, (int) round((float) $invoice->total), null, (string) $invoice->status);
            }
        }

        if (Schema::hasTable('core_licenses')) {
            $licenses = CoreLicense::query()->latest();
            if ($domain) {
                $licenses->where('domain', $domain);
            }
            foreach ($licenses->limit(30)->get() as $license) {
                $meta = is_array($license->meta) ? $license->meta : [];
                $due = (int) ($meta['balance_due'] ?? $meta['renewal_amount'] ?? 0);
                if ($due < 1000) {
                    continue;
                }
                $bills[] = $this->row('license', (string) $license->id, 'لایسنس '.$license->domain, $due, (string) $license->domain, (string) $license->status);
            }
        }

        if (Schema::hasTable('sales_catalog_items')) {
            foreach (SalesCatalogItem::query()->where('type', 'subscription')->where('status', 'active')->limit(30)->get() as $item) {
                $offers[] = $this->row('subscription', (string) $item->id, 'اشتراک: '.$item->name, (int) round((float) $item->price), $domain, 'offer');
            }
        }

        return ['bills' => $bills, 'offers' => $offers];
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function salesInvoice(string $id): array
    {
        $invoice = SalesInvoice::query()->find($id);
        if (! $invoice) {
            throw new PaymentException('فاکتور فروش پیدا نشد.', 'not_found', 404);
        }

        return $this->bill('sales_invoice', (string) $invoice->id, null, (int) round((float) $invoice->total), 'فاکتور فروش '.$invoice->number, (string) $invoice->status === 'paid');
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function accountingInvoice(string $id): array
    {
        $invoice = AccountingInvoice::query()->find($id);
        if (! $invoice) {
            throw new PaymentException('فاکتور حسابداری پیدا نشد.', 'not_found', 404);
        }

        return $this->bill('accounting_invoice', (string) $invoice->id, null, (int) round((float) $invoice->total), 'فاکتور حسابداری '.$invoice->number, (string) $invoice->status === 'paid');
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function projectInvoice(string $id): array
    {
        $invoice = ProInvoice::query()->find($id);
        if (! $invoice) {
            throw new PaymentException('پیش‌فاکتور پیدا نشد.', 'not_found', 404);
        }
        $total = (float) $invoice->total - (float) ($invoice->discount ?? 0);

        return $this->bill('project_invoice', (string) $invoice->id, null, (int) round($total), 'پیش‌فاکتور '.$invoice->number, (string) $invoice->status === 'paid');
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function marketplaceOrder(string $id): array
    {
        $order = MarketplaceOrder::query()->find($id);
        if (! $order) {
            throw new PaymentException('سفارش بازارچه پیدا نشد.', 'not_found', 404);
        }
        $domain = null;
        if ($order->site_provision_id && Schema::hasTable('webino_site_provisions')) {
            $domain = WebinoSiteProvision::query()->whereKey($order->site_provision_id)->value('domain');
        }
        $paid = in_array((string) $order->status, ['paid', 'fulfilled'], true);

        return $this->bill('marketplace_order', (string) $order->id, $this->domain($domain), (int) round((float) $order->total), 'سفارش بازارچه '.$order->order_number, $paid);
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function smsPackage(string $id, ?string $domain): array
    {
        if ($domain === null) {
            throw new PaymentException('دامنه سایت برای شارژ پیامک الزامی است.', 'domain');
        }
        $package = ModirPayamakPackage::query()->find($id);
        if (! $package || ! $package->is_active) {
            throw new PaymentException('بسته پیامک پیدا نشد.', 'not_found', 404);
        }
        $order = ModirPayamakOrder::query()
            ->where('domain', $domain)
            ->where('package_id', $package->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        if (! $order) {
            $order = ModirPayamakOrder::query()->create([
                'domain' => $domain,
                'package_id' => $package->id,
                'amount' => $package->amount,
                'status' => 'pending',
            ]);
        }

        return $this->bill(
            'sms_order',
            (string) $order->id,
            $domain,
            (int) round((float) $order->amount),
            'شارژ پیامک '.$package->name,
            false,
            ['package_id' => $package->id, 'sms_units' => (int) $package->sms_units, 'requested_type' => 'sms_credit'],
        );
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function smsOrder(string $id): array
    {
        $order = ModirPayamakOrder::query()->find($id);
        if (! $order) {
            throw new PaymentException('سفارش شارژ پیامک پیدا نشد.', 'not_found', 404);
        }

        return $this->bill('sms_order', (string) $order->id, (string) $order->domain, (int) round((float) $order->amount), 'شارژ پیامک', (string) $order->status === 'paid', [
            'package_id' => $order->package_id,
        ]);
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function license(string $id): array
    {
        $license = CoreLicense::query()->find($id);
        if (! $license) {
            throw new PaymentException('لایسنس پیدا نشد.', 'not_found', 404);
        }
        $meta = is_array($license->meta) ? $license->meta : [];
        $amount = (int) ($meta['balance_due'] ?? $meta['renewal_amount'] ?? 0);

        return $this->bill('license', (string) $license->id, (string) $license->domain, $amount, 'تمدید لایسنس '.$license->domain, $amount < 1000 && ! empty($meta['last_payment_id']));
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function subscription(string $id, ?string $domain): array
    {
        $item = SalesCatalogItem::query()->find($id);
        if (! $item || ($item->type !== null && $item->type !== 'subscription')) {
            throw new PaymentException('اشتراک پیدا نشد.', 'not_found', 404);
        }

        return $this->bill('subscription', (string) $item->id, $domain, (int) round((float) $item->price), 'اشتراک '.$item->name, false);
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function wallet(string $id, ?string $domain, mixed $amount): array
    {
        $domain = $this->domain($domain ?: $id);
        if ($domain === null) {
            throw new PaymentException('دامنه کیف پول الزامی است.', 'domain');
        }

        return $this->bill('wallet_topup', $domain, $domain, (int) round((float) $amount), 'شارژ کیف پول '.$domain, false);
    }

    /**
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function generic(string $id, mixed $amount, mixed $description, ?string $domain): array
    {
        $payableId = $id !== '' ? $id : 'generic';

        return $this->bill('generic', $payableId, $domain, (int) round((float) $amount), (string) ($description ?: 'پرداخت'), false);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{type:string,id:string,domain:?string,base_amount:int,description:string,title:string,already_paid:bool,meta:array<string,mixed>}
     */
    private function bill(string $type, string $id, ?string $domain, int $amount, string $title, bool $paid, array $meta = []): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'domain' => $domain,
            'base_amount' => $amount,
            'description' => $title,
            'title' => $title,
            'already_paid' => $paid,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $type, string $id, string $title, int $amount, ?string $domain, string $status): array
    {
        return [
            'payable_type' => $type,
            'payable_id' => $id,
            'title' => $title,
            'amount' => $amount,
            'currency' => 'IRR',
            'domain' => $domain,
            'status' => $status,
        ];
    }

    private function domain(mixed $domain): ?string
    {
        $value = trim((string) $domain);
        if ($value === '') {
            return null;
        }

        return CoreLicenseResolver::normalizeDomain($value);
    }
}
