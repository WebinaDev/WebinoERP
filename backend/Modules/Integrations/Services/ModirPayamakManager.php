<?php

namespace Modules\Integrations\Services;

use Modules\Integrations\Entities\ModirPayamakAccount;
use Modules\Integrations\Entities\ModirPayamakBalanceLedger;
use Modules\Integrations\Entities\ModirPayamakDomainNumber;
use Modules\Integrations\Entities\ModirPayamakMessage;
use Illuminate\Support\Facades\Schema;
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
        'return-requested',
        'return-approved',
        'return-rejected',
        'return-received',
        'return-refunded',
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
            abort(422, __('messages.domain_is_required'));
        }
        $account = $this->getOrCreateAccount($domain);
        if ($account->status !== 'active') {
            abort(403, __('messages.modirpayamak_account_suspended'));
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
            abort(402, __('messages.insufficient_modirpayamak_balance'));
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
            ['name' => 'بستهٔ پایه', 'amount' => 100000, 'sms_units' => 200, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'بستهٔ تجاری', 'amount' => 500000, 'sms_units' => 1100, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAccountPublic(ModirPayamakAccount $account): array
    {
        $numbers = [];
        if (Schema::hasTable('modirpayamak_domain_numbers')) {
            try {
                $numbers = ModirPayamakDomainNumber::query()
                    ->where('domain', $account->domain)
                    ->orderBy('role')
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ModirPayamakDomainNumber $n) => $this->formatDomainNumber($n))
                    ->all();
            } catch (\Throwable) {
                $numbers = [];
            }
        }

        return [
            'id' => $account->id,
            'domain' => $account->domain,
            'balance' => (float) $account->balance,
            'default_from' => $account->default_from,
            'status' => $account->status,
            'expires_at' => null,
            'numbers' => $numbers,
            'domain_numbers_ready' => Schema::hasTable('modirpayamak_domain_numbers'),
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
        if (! Schema::hasTable('modirpayamak_domain_numbers')) {
            abort(503, 'modirpayamak_domain_numbers table is missing; run php artisan migrate --force');
        }
        $domain = $this->normalizeDomain($domain);
        $number = trim($number);
        $role = $this->normalizeNumberRole($role);
        $label = trim($label);
        if ($domain === '' || $number === '') {
            abort(422, __('messages.domain_and_number_are_required'));
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
        if (! Schema::hasTable('modirpayamak_domain_numbers')) {
            return;
        }
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
        if (! Schema::hasTable('modirpayamak_domain_numbers')) {
            return [];
        }
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
            abort(422, __('messages.domain_event_and_pattern_code_are_required'));
        }
        if (! in_array($scope, self::PATTERN_SCOPES, true)) {
            abort(422, __('messages.invalid_pattern_scope'));
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

    /**
     * Persist a tenant send so the site dashboard and reports can list it.
     *
     * @param  list<string>  $recipients
     * @param  array<string, mixed>  $edge
     */
    public function recordMessage(
        string $domain,
        string $sendingType,
        string $message,
        array $recipients,
        string $from,
        string $status,
        float $cost,
        ?string $patternCode = null,
        array $edge = [],
    ): void {
        if (! Schema::hasTable('modirpayamak_messages')) {
            return;
        }
        $domain = $this->normalizeDomain($domain);
        try {
            ModirPayamakMessage::query()->create([
                'domain' => $domain,
                'sending_type' => $sendingType !== '' ? $sendingType : 'webservice',
                'message' => $message,
                'recipients' => array_values($recipients),
                'from_number' => $from,
                'status' => $status !== '' ? $status : 'sent',
                'cost' => $cost,
                'pattern_code' => $patternCode,
                'edge_payload' => $edge,
            ]);
        } catch (\Throwable) {
            // History must not fail a send that already left the provider.
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentMessages(string $domain, int $limit = 20): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '' || ! Schema::hasTable('modirpayamak_messages')) {
            return [];
        }
        $limit = min(100, max(1, $limit));

        return ModirPayamakMessage::query()
            ->where('domain', $domain)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ModirPayamakMessage $m) {
                $edge = is_array($m->edge_payload) ? $m->edge_payload : [];
                $recipients = is_array($m->recipients) ? array_values($m->recipients) : [];

                return [
                    'id' => $m->id,
                    'messages_outbox_id' => $m->id,
                    'domain' => $m->domain,
                    'sending_type' => $m->sending_type ?: 'webservice',
                    'type' => $m->sending_type ?: 'webservice',
                    'message' => (string) ($m->message ?? ''),
                    'recipients' => $recipients,
                    'recipient' => $recipients[0] ?? '',
                    'from_number' => (string) ($m->from_number ?? ''),
                    'number' => (string) ($m->from_number ?? ''),
                    'status' => (string) ($m->status ?? 'sent'),
                    'state' => (string) ($m->status ?? 'sent'),
                    'cost' => (float) $m->cost,
                    'pattern_code' => $m->pattern_code,
                    'event_key' => $edge['event_key'] ?? null,
                    'role' => $edge['role'] ?? null,
                    'rcpts_count' => (string) count($recipients),
                    'exit_count' => '',
                    'created_at' => optional($m->created_at)?->toIso8601String(),
                    'time' => optional($m->created_at)?->getTimestamp(),
                    'source' => 'erp',
                ];
            })
            ->all();
    }

    /**
     * Local ledger first, then provider outbox rows the ledger does not already cover.
     *
     * @return list<array<string, mixed>>
     */
    public function sentHistory(string $domain, int $limit = 20): array
    {
        $limit = min(100, max(1, $limit));
        $local = $this->recentMessages($domain, $limit);
        $remote = [];
        try {
            $edge = app(ModirPayamakEdgeClient::class);
            $res = $edge->reportOutbox(1, max($limit, 20));
            if (! empty($res['ok'])) {
                $remote = ModirPayamakReportRows::messages($res['data'] ?? null);
            }
        } catch (\Throwable) {
            $remote = [];
        }

        return $this->mergeSent($local, $remote, $limit);
    }

    /**
     * @param  list<array<string, mixed>>  $local
     * @param  list<array<string, mixed>>  $remote
     * @return list<array<string, mixed>>
     */
    public function mergeSent(array $local, array $remote, int $limit): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($local, $remote) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['messages_outbox_id'] ?? $row['id'] ?? '')
                .'|'.(string) ($row['message'] ?? '')
                .'|'.(string) ($row['created_at'] ?? $row['time'] ?? '')
                .'|'.(string) ($row['recipient'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
