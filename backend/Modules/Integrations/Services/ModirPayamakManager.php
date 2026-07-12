<?php

namespace Modules\Integrations\Services;

use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakBalanceLedger;
use Modules\Integrations\Entities\ModirPayamakPackage;

class ModirPayamakManager
{
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0] ?? $domain;

        return $domain;
    }

    public function getOrCreateAccount(string $domain): ModirPayamakAccount
    {
        $domain = $this->normalizeDomain($domain);

        return ModirPayamakAccount::query()->firstOrCreate(
            ['domain' => $domain],
            ['balance' => 0, 'default_from' => app(ModirPayamakEdgeClient::class)->defaultFrom(), 'status' => 'active']
        );
    }

    public function assertLicensedDomain(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            abort(422, 'Domain is required');
        }
        $account = $this->getOrCreateAccount($domain);
        if ($account->status !== 'active') {
            abort(403, 'ModirPayamak account suspended');
        }
    }

    public function credit(string $domain, float $amount, string $type, ?string $reference = null, array $meta = []): ModirPayamakAccount
    {
        $account = $this->getOrCreateAccount($domain);
        $account->balance = (float) $account->balance + $amount;
        $account->save();
        ModirPayamakBalanceLedger::create([
            'domain' => $account->domain,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $account->balance,
            'reference' => $reference,
            'meta' => $meta,
        ]);

        return $account->fresh();
    }

    public function debit(string $domain, float $amount, string $type, ?string $reference = null, array $meta = []): ModirPayamakAccount
    {
        $account = $this->getOrCreateAccount($domain);
        if ((float) $account->balance < $amount) {
            abort(402, 'Insufficient ModirPayamak balance');
        }

        return $this->credit($domain, -$amount, $type, $reference, $meta);
    }

    public function pricePerUnit(): float
    {
        return max(1, (float) config('integrations.modirpayamak.sms_price_per_unit', 500));
    }

    public function seedDefaultPackages(): void
    {
        if (ModirPayamakPackage::query()->exists()) {
            return;
        }
        ModirPayamakPackage::insert([
            ['name' => 'Starter', 'amount' => 100000, 'sms_units' => 200, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Business', 'amount' => 500000, 'sms_units' => 1100, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
