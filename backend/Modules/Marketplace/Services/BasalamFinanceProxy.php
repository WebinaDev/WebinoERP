<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\Http;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;

/**
 * Vendor booth balance and settlements.
 *
 * The shop dashboard must not call accounting.basalam.com itself: that host
 * answers 403 for tokens presented from the merchant site. ERP holds the
 * OAuth client and forwards the vendor token.
 */
class BasalamFinanceProxy
{
    private const BALANCE_URL = 'https://accounting.basalam.com/financial/v1/client/transaction/balance';

    private const SETTLEMENTS_URL = 'https://accounting.basalam.com/financial/v1/client/balance-settlement';

    private const SETTLEMENT_CREATE_URL = 'https://accounting.basalam.com/financial/v2/client/balance-settlement';

    private const BANKS_URL = 'https://identity.basalam.com/v1/users/bank-accounts';

    /** @var list<int> */
    private const ACTIVE_STATUSES = [4, 6, 8, 9, 10];

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, body: array<string, mixed>}
     */
    public function call(array $input): array
    {
        $siteUrl = rtrim((string) ($input['site_url'] ?? ''), '/');
        $token = trim((string) ($input['access_token'] ?? ''));
        if ($token === '') {
            $token = $this->storedAccessToken($siteUrl);
        }
        if ($token === '') {
            return [
                'status' => 409,
                'body' => [
                    'message' => 'توکن باسلام برای این سایت روی ERP نیست. اتصال غرفه را دوباره انجام دهید.',
                ],
            ];
        }

        $this->rememberToken($siteUrl, $token);

        $op = (string) ($input['op'] ?? '');
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = max(1, (int) ($input['per_page'] ?? 20));

        if ($op === 'balance') {
            return $this->send('GET', self::BALANCE_URL, $token);
        }
        if ($op === 'active_settlements') {
            return $this->send('GET', $this->settlementsUrl($page, $perPage, true), $token);
        }
        if ($op === 'history') {
            return $this->send('GET', $this->settlementsUrl($page, $perPage, false), $token);
        }
        if ($op === 'banks') {
            return $this->send('GET', self::BANKS_URL, $token);
        }
        if ($op === 'create_settlement') {
            $body = [
                'amount' => (int) ($input['amount'] ?? 0),
                'method' => (int) ($input['settlement_method'] ?? 0),
            ];
            if (! empty($input['investment_option_id'])) {
                $body['investment_option_id'] = (int) $input['investment_option_id'];
            }
            if (! empty($input['bank_account_id'])) {
                $body['bank_account_id'] = (int) $input['bank_account_id'];
            }

            return $this->send('POST', self::SETTLEMENT_CREATE_URL, $token, $body);
        }

        return [
            'status' => 422,
            'body' => ['message' => 'عملیات مالی نامعتبر است.'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    private function send(string $method, string $url, string $token, ?array $body = null): array
    {
        $pending = Http::timeout(20)
            ->acceptJson()
            ->withToken($token)
            ->withHeaders([
                'user-agent' => 'Wp-Basalam',
                'referer' => 'https://webina.dev',
            ]);

        $response = $method === 'POST'
            ? $pending->asJson()->post($url, $body ?? [])
            : $pending->get($url);

        $decoded = $response->json();

        return [
            'status' => $response->status() > 0 ? $response->status() : 502,
            'body' => is_array($decoded) ? $decoded : [
                'message' => 'پاسخ باسلام قابل خواندن نیست.',
            ],
        ];
    }

    private function settlementsUrl(int $page, int $perPage, bool $active): string
    {
        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];
        if ($active) {
            $params['status_filter_type'] = 2;
        }
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($active) {
            foreach (self::ACTIVE_STATUSES as $status) {
                $query .= '&status='.rawurlencode((string) $status);
            }
        }

        return self::SETTLEMENTS_URL.'?'.$query;
    }

    private function storedAccessToken(string $siteUrl): string
    {
        $row = $this->findConnection($siteUrl);
        $tokens = is_array($row?->tokens) ? $row->tokens : [];
        $token = $tokens['access_token'] ?? '';

        return is_string($token) ? trim($token) : '';
    }

    private function rememberToken(string $siteUrl, string $token): void
    {
        $row = $this->findConnection($siteUrl);
        if (! $row) {
            return;
        }
        $tokens = is_array($row->tokens) ? $row->tokens : [];
        if (($tokens['access_token'] ?? '') === $token) {
            return;
        }
        $tokens['access_token'] = $token;
        $row->tokens = $tokens;
        $row->save();
    }

    private function findConnection(string $siteUrl): ?MarketplaceBasalamConnection
    {
        if ($siteUrl === '') {
            return null;
        }
        $exact = MarketplaceBasalamConnection::query()->where('site_url', $siteUrl)->first();
        if ($exact) {
            return $exact;
        }

        $host = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: '';
        if ($host === '') {
            return null;
        }

        return MarketplaceBasalamConnection::query()
            ->where('site_url', 'like', '%'.$host.'%')
            ->get()
            ->first(function (MarketplaceBasalamConnection $row) use ($host) {
                $rowHost = strtolower((string) parse_url((string) $row->site_url, PHP_URL_HOST));
                $rowHost = preg_replace('/^www\./', '', $rowHost) ?: '';

                return $rowHost === $host;
            });
    }
}
