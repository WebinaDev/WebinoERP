<?php

namespace Modules\Integrations\Services\Payments;

use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Core\Entities\CoreLicense;
use Modules\Integrations\Entities\ModirPayamakOrder;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Entities\PaymentWallet;
use Modules\Integrations\Entities\PaymentWalletLedger;
use Modules\Integrations\Services\ModirPayamakManager;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Marketplace\Services\MarketplaceLicenseService;
use Modules\Projects\Entities\ProInvoice;
use Modules\Sales\Entities\SalesInvoice;
use Throwable;

class PaymentSideEffects
{
    public function __construct(private ModirPayamakManager $sms) {}

    public function apply(PaymentIntent $intent): void
    {
        if ($intent->side_effects_at !== null) {
            return;
        }

        match ($intent->payable_type) {
            'sales_invoice' => $this->salesInvoice($intent),
            'accounting_invoice' => $this->accountingInvoice($intent),
            'project_invoice' => $this->projectInvoice($intent),
            'marketplace_order' => $this->marketplace($intent),
            'sms_order' => $this->smsCredit($intent),
            'license' => $this->license($intent),
            'subscription' => $this->subscription($intent),
            'wallet_topup' => $this->wallet($intent),
            default => null,
        };

        $intent->forceFill(['side_effects_at' => now()])->save();
    }

    private function salesInvoice(PaymentIntent $intent): void
    {
        $invoice = SalesInvoice::query()->find($intent->payable_id);
        if (! $invoice || (string) $invoice->status === 'paid') {
            return;
        }
        $invoice->update([
            'status' => 'paid',
            'payment_method' => $intent->mode.':'.$intent->gateway,
        ]);
    }

    private function accountingInvoice(PaymentIntent $intent): void
    {
        $invoice = AccountingInvoice::query()->find($intent->payable_id);
        if (! $invoice || (string) $invoice->status === 'paid') {
            return;
        }
        $invoice->update(['status' => 'paid']);
    }

    private function projectInvoice(PaymentIntent $intent): void
    {
        $invoice = ProInvoice::query()->find($intent->payable_id);
        if (! $invoice || (string) $invoice->status === 'paid') {
            return;
        }
        $invoice->update(['status' => 'paid']);
    }

    private function marketplace(PaymentIntent $intent): void
    {
        $order = MarketplaceOrder::query()->find($intent->payable_id);
        if (! $order) {
            return;
        }
        if (! in_array((string) $order->status, ['paid', 'fulfilled'], true)) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_gateway' => $intent->gateway,
                'payment_ref' => $intent->provider_ref ?: $intent->authority,
            ]);
        }
        try {
            app(MarketplaceLicenseService::class)->grantFromOrder($order->fresh('items'));
        } catch (Throwable $e) {
            $meta = $intent->meta ?? [];
            $meta['grant_error'] = $e->getMessage();
            $intent->meta = $meta;
            $intent->save();
        }
    }

    private function smsCredit(PaymentIntent $intent): void
    {
        $order = ModirPayamakOrder::query()->find($intent->payable_id);
        if (! $order || (string) $order->status === 'paid') {
            return;
        }
        $package = $order->package_id ? ModirPayamakPackage::query()->find($order->package_id) : null;
        $units = $package && (int) $package->sms_units > 0
            ? (float) $package->sms_units
            : (float) $order->amount / $this->sms->pricePerUnit();
        $order->update([
            'status' => 'paid',
            'ref_id' => $intent->provider_ref ?: $intent->public_id,
            'authority' => $intent->authority,
        ]);
        $this->sms->credit((string) $order->domain, $units, 'topup', (string) $order->id, [
            'package_id' => $order->package_id,
            'payment_id' => $intent->public_id,
        ]);
    }

    private function license(PaymentIntent $intent): void
    {
        $license = CoreLicense::query()->find($intent->payable_id);
        if (! $license) {
            return;
        }
        $meta = is_array($license->meta) ? $license->meta : [];
        $meta['balance_due'] = 0;
        $meta['last_payment_id'] = $intent->public_id;
        $meta['last_paid_at'] = now()->toIso8601String();
        $license->meta = $meta;
        $license->save();
    }

    private function subscription(PaymentIntent $intent): void
    {
        $meta = $intent->meta ?? [];
        $meta['subscription_paid'] = true;
        $intent->meta = $meta;
        $intent->save();
    }

    private function wallet(PaymentIntent $intent): void
    {
        $domain = (string) ($intent->domain ?: $intent->payable_id);
        $wallet = PaymentWallet::query()->firstOrCreate(['domain' => $domain], ['balance' => 0]);
        $exists = PaymentWalletLedger::query()->where('payment_intent_id', $intent->id)->exists();
        if ($exists) {
            return;
        }
        $wallet->balance = (float) $wallet->balance + (int) $intent->base_amount;
        $wallet->save();
        PaymentWalletLedger::query()->create([
            'domain' => $domain,
            'amount' => (int) $intent->base_amount,
            'balance_after' => $wallet->balance,
            'payment_intent_id' => $intent->id,
            'note' => 'wallet top-up',
        ]);
    }
}
