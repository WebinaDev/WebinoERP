<?php

namespace Modules\Integrations\Services;

use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakBalanceLedger;
use Modules\Integrations\Entities\ModirPayamakDomainNumber;
use Modules\Integrations\Entities\ModirPayamakPackage;
use Modules\Integrations\Entities\ModirPayamakPatternRegistry;

class ModirPayamakManager
{
    public const ROLE_SERVICE = 'service';

    public const ROLE_PERSONAL = 'personal';

    public const PATTERN_SCOPES = ['order_customer', 'order_admin', 'site'];

    public const ORDER_EVENTS = [
        'pending_on_create',
        'pending_on_status',
        'on-hold',
        'processing',
        'packaged',
        'sent-to-warehouse',
        'courier',
        'post',
        'tipax',
        'completed',
        'cancelled',
        'failed',
        'refunded',
        'checkout-draft',
        'cart-abandoned',
        'order-abandoned',
        'user-welcome',
        'stock-low',
        'stock-out',
    ];

    public const SITE_EVENTS = ['otp_login', 'otp_register'];

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

    /**
     * @return array<string, mixed>
     */
    public function formatAccountPublic(ModirPayamakAccount $account): array
    {
        $numbers = ModirPayamakDomainNumber::query()
            ->where('domain', $account->domain)
            ->orderBy('role')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (ModirPayamakDomainNumber $n) => $this->formatDomainNumber($n))
            ->all();

        return [
            'id' => $account->id,
            'domain' => $account->domain,
            'balance' => (float) $account->balance,
            'default_from' => $account->default_from,
            'status' => $account->status,
            'expires_at' => null,
            'numbers' => $numbers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDomainNumber(ModirPayamakDomainNumber $row): array
    {
        $role = $row->role === 'marketing' ? self::ROLE_PERSONAL : $row->role;

        return [
            'id' => $row->id,
            'domain' => $row->domain,
            'number' => $row->number,
            'role' => $role,
            'label' => $row->label,
            'is_default' => (bool) $row->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPackage(ModirPayamakPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'amount' => (float) $package->amount,
            'bonus' => (int) $package->sms_units,
            'sms_units' => (int) $package->sms_units,
            'sort' => (int) $package->sort_order,
            'sort_order' => (int) $package->sort_order,
            'status' => $package->is_active ? 'active' : 'inactive',
            'is_active' => (bool) $package->is_active,
        ];
    }

    public function normalizeNumberRole(string $role): string
    {
        $role = strtolower(trim($role));
        if ($role === 'marketing' || $role === self::ROLE_PERSONAL) {
            return self::ROLE_PERSONAL;
        }

        return self::ROLE_SERVICE;
    }

    /**
     * @return array<string, mixed>
     */
    public function attachDomainNumber(string $domain, string $number, string $role = self::ROLE_SERVICE, string $label = '', bool $makeDefault = true): array
    {
        $domain = $this->normalizeDomain($domain);
        $number = trim($number);
        $role = $this->normalizeNumberRole($role);
        $label = trim($label);
        if ($domain === '' || $number === '') {
            abort(422, 'Domain and number are required');
        }

        $this->getOrCreateAccount($domain);

        $existing = ModirPayamakDomainNumber::query()
            ->where('domain', $domain)
            ->where('number', $number)
            ->first();

        $isDefault = $makeDefault;
        if ($makeDefault) {
            ModirPayamakDomainNumber::query()
                ->where('domain', $domain)
                ->where('role', $role)
                ->update(['is_default' => false]);
        } elseif (! $existing) {
            $hasDefault = ModirPayamakDomainNumber::query()
                ->where('domain', $domain)
                ->where('role', $role)
                ->where('is_default', true)
                ->exists();
            $isDefault = ! $hasDefault;
        } else {
            $isDefault = (bool) $existing->is_default;
        }

        $payload = [
            'number' => $number,
            'role' => $role,
            'label' => $label,
            'is_default' => $isDefault,
        ];

        if ($existing) {
            $existing->update($payload);
            $row = $existing->fresh();
        } else {
            $row = ModirPayamakDomainNumber::query()->create(array_merge($payload, ['domain' => $domain]));
        }

        if ($role === self::ROLE_SERVICE && $isDefault) {
            ModirPayamakAccount::query()->where('domain', $domain)->update(['default_from' => $number]);
        }

        return $this->formatDomainNumber($row);
    }

    public function detachDomainNumber(string $domain, string $role, ?string $number = null): void
    {
        $domain = $this->normalizeDomain($domain);
        $role = $this->normalizeNumberRole($role);
        $number = $number !== null ? trim($number) : '';

        $query = ModirPayamakDomainNumber::query()->where('domain', $domain);
        if ($number !== '') {
            $query->where('number', $number)->where('role', $role);
            $query->delete();
        } else {
            $default = (clone $query)->where('role', $role)->orderByDesc('is_default')->orderBy('id')->first();
            $default?->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNumberAttachments(string $number): array
    {
        $number = trim($number);
        if ($number === '') {
            return [];
        }

        return ModirPayamakDomainNumber::query()
            ->where('number', $number)
            ->orderBy('domain')
            ->get()
            ->map(fn (ModirPayamakDomainNumber $n) => $this->formatDomainNumber($n))
            ->all();
    }

    /**
     * @param  array<string, string>|null  $paramMap
     * @return array<string, mixed>
     */
    public function attachPattern(string $domain, string $scope, string $eventKey, string $patternCode, ?array $paramMap = null): array
    {
        $domain = $this->normalizeDomain($domain);
        $scope = strtolower(trim($scope));
        $eventKey = trim($eventKey);
        $patternCode = trim($patternCode);

        if ($domain === '' || $eventKey === '' || $patternCode === '') {
            abort(422, 'Domain, event and pattern code are required');
        }
        if (! in_array($scope, self::PATTERN_SCOPES, true)) {
            abort(422, 'Invalid pattern scope');
        }

        $this->getOrCreateAccount($domain);

        $row = ModirPayamakPatternRegistry::query()->updateOrCreate(
            ['domain' => $domain, 'scope' => $scope, 'event_key' => $eventKey],
            [
                'ippanel_code' => $patternCode,
                'param_map' => $paramMap,
                'sync_status' => 'synced',
                'last_error' => null,
                'submitted_at' => now(),
            ]
        );

        return $row->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detachPattern(string $domain, string $scope, string $eventKey): array
    {
        $domain = $this->normalizeDomain($domain);
        $scope = strtolower(trim($scope));
        $eventKey = trim($eventKey);

        ModirPayamakPatternRegistry::query()
            ->where('domain', $domain)
            ->where('scope', $scope)
            ->where('event_key', $eventKey)
            ->delete();

        return $this->listPatternRegistry($domain);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPatternRegistry(string $domain = '', int $limit = 200, int $offset = 0): array
    {
        $query = ModirPayamakPatternRegistry::query()->orderByDesc('updated_at');
        $domain = $this->normalizeDomain($domain);
        if ($domain !== '') {
            $query->where('domain', $domain);
        } else {
            $query->limit($limit)->offset($offset);
        }

        return $query->get()->map(fn (ModirPayamakPatternRegistry $r) => [
            'domain' => $r->domain,
            'scope' => $r->scope,
            'event_key' => $r->event_key,
            'ippanel_code' => $r->ippanel_code,
            'sync_status' => $r->sync_status,
            'last_error' => $r->last_error,
            'param_map' => $r->param_map,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLedger(string $domain, int $page = 1, int $limit = 50): array
    {
        $domain = $this->normalizeDomain($domain);
        $limit = min(100, max(1, $limit));
        $page = max(1, $page);

        return ModirPayamakBalanceLedger::query()
            ->where('domain', $domain)
            ->orderByDesc('id')
            ->forPage($page, $limit)
            ->get()
            ->map(fn (ModirPayamakBalanceLedger $row) => [
                'id' => $row->id,
                'type' => $row->type,
                'amount' => (float) $row->amount,
                'balance_after' => (float) $row->balance_after,
                'note' => is_array($row->meta) ? (string) ($row->meta['note'] ?? '') : '',
                'created_at' => optional($row->created_at)?->toIso8601String(),
            ])
            ->all();
    }
}
