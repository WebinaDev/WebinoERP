<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreLicense;
use Modules\Integrations\Entities\ModirPayamakMessage;
use Modules\Integrations\Services\ModirPayamakManager;

/**
 * WordPress-era proxy for tenant Dashboard SMS panel.
 * GET/POST /api/webinocrm/v1/modirpayamak/{path}
 *
 * Auth: domain (+ optional license_key matching CoreLicense).
 * Response: { ok: bool, ... } — not wrapped in Laravel ApiResponseFormatter.
 */
class WebinocrmModirPayamakCompatController extends Controller
{
    /** @var array<string, string> */
    private const ALIASES = [
        'dashboard' => 'tenantDashboard',
        'ledger' => 'customerLedger',
        'settings/shop' => 'shopSettings',
    ];

    public function __construct(
        private ModirPayamakManager $manager,
        private ModirPayamakCustomerController $customer,
        private ModirPayamakAdminController $admin,
    ) {}

    public function handle(Request $request, string $path = ''): JsonResponse
    {
        try {
            $domain = $this->resolveDomain($request);
            $request->merge(['domain' => $domain]);
            $path = trim($path, '/');

            if ($path === '') {
                return $this->ok(['hint' => 'ModirPayamak legacy API']);
            }

            if (isset(self::ALIASES[$path])) {
                return $this->{self::ALIASES[$path]}($request, $domain);
            }

            if ($this->isUnavailablePath($path)) {
                return $this->unavailable("Path not implemented: {$path}");
            }

            return $this->forward($request, $path);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->unavailable($e->getMessage() ?: 'Request failed', $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->unavailable($e->getMessage() ?: 'Request failed');
        }
    }

    protected function tenantDashboard(Request $request, string $domain): JsonResponse
    {
        $account = $this->manager->getOrCreateAccount($domain);
        $messages = ModirPayamakMessage::query()
            ->where('domain', $domain)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ModirPayamakMessage $m) => [
                'id' => $m->id,
                'domain' => $m->domain,
                'sending_type' => $m->sending_type ?? 'webservice',
                'cost' => (float) ($m->cost ?? 0),
                'status' => $m->status ?? 'sent',
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();

        return $this->ok([
            'account' => $this->manager->formatAccountPublic($account),
            'messages' => $messages,
            'balance' => (float) $account->balance,
        ]);
    }

    protected function customerLedger(Request $request, string $domain): JsonResponse
    {
        return $this->adapt($this->admin->customerLedger($request));
    }

    protected function shopSettings(Request $request, string $domain): JsonResponse
    {
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            return $this->ok(['saved' => true, 'settings' => []]);
        }

        return $this->ok([
            'provider' => 'modirpayamak',
            'settings' => [
                'enabled' => true,
                'events' => [],
            ],
            'event_keys' => $this->manager::ORDER_EVENTS,
            'templates' => [],
            'shortcodes' => [],
            'registry' => [],
        ]);
    }

    protected function forward(Request $request, string $path): JsonResponse
    {
        $segments = explode('/', $path);
        $resource = $segments[0] ?? '';

        $response = match ($resource) {
            'account', 'packages', 'topup', 'send', 'reports', 'patterns', 'numbers', 'phonebooks' => $this->forwardCustomer($request, $path),
            'secretaries', 'orders' => $this->forwardAdmin($request, $path),
            default => null,
        };

        if ($response === null) {
            return $this->unavailable("Unknown path: {$path}");
        }

        return $this->adapt($response);
    }

    protected function forwardCustomer(Request $request, string $path): JsonResponse
    {
        $method = match ($path) {
            'account' => 'account',
            'packages' => 'packages',
            'topup/init' => 'topupInit',
            'topup/verify' => 'topupVerify',
            'send' => 'send',
            'send/peer-to-peer' => 'sendPeerToPeer',
            'send/calculate-price' => 'calculatePrice',
            'reports/messages' => 'reportsMessages',
            'reports/outbox' => 'reportsOutbox',
            'patterns' => 'patterns',
            'numbers' => 'numbers',
            'phonebooks' => 'phonebooks',
            default => null,
        };

        if ($method === null && str_starts_with($path, 'phonebooks/') && str_ends_with($path, '/contacts')) {
            if ($request->filled('phone') && ! $request->filled('number')) {
                $request->merge(['number' => $request->input('phone')]);
            }

            return $this->customer->phonebookContacts($request, (int) explode('/', $path)[1]);
        }

        if ($method === null && str_starts_with($path, 'reports/outbox/')) {
            return $this->customer->reportOutboxDetail($request, (string) explode('/', $path)[2]);
        }

        if ($method === null) {
            abort(404, "Unknown customer path: {$path}");
        }

        return $this->customer->{$method}($request);
    }

    protected function forwardAdmin(Request $request, string $path): JsonResponse
    {
        if ($path === 'secretaries') {
            if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
                return $this->admin->secretariesStore($request);
            }

            return $this->admin->secretariesIndex($request);
        }

        return match ($path) {
            'secretaries/delete' => $this->admin->secretariesDestroy($request),
            'secretaries/process' => $this->ok(['processed' => 0, 'matched' => 0, 'skipped' => true]),
            'orders/notify', 'orders/test-notify' => $this->ok(['results' => [], 'skipped' => true, 'reason' => 'not_configured']),
            'reports/inbox' => $this->admin->reportsInbox($request),
            default => abort(404, "Unknown admin path: {$path}"),
        };
    }

    protected function isUnavailablePath(string $path): bool
    {
        $prefixes = [
            'templates',
            'drafts',
            'newsletter',
            'auth/',
            'patterns/sync',
            'patterns/registry',
            'patterns/detach',
            'phonebooks/edge',
            'reports/bulk-',
            'send/cancel-scheduled',
            'site/settings/sms',
        ];

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveDomain(Request $request): string
    {
        $domain = $this->manager->normalizeDomain((string) ($request->input('domain') ?? $request->query('domain', '')));
        if ($domain === '') {
            abort(422, 'Domain is required');
        }

        $licenseKey = $request->input('license_key') ?? $request->query('license_key');
        if (filled($licenseKey)) {
            $valid = CoreLicense::query()
                ->where('domain', $domain)
                ->where('license_key', $licenseKey)
                ->where('status', 'active')
                ->exists();
            if (! $valid) {
                abort(403, 'Invalid license for domain');
            }
        }

        $this->manager->assertLicensedDomain($domain);

        return $domain;
    }

    protected function adapt(JsonResponse $response): JsonResponse
    {
        $status = $response->getStatusCode();
        $content = $response->getData(true);

        if ($status >= 400) {
            $message = is_array($content)
                ? (string) ($content['message'] ?? $content['error'] ?? 'Request failed')
                : 'Request failed';

            return $this->unavailable($message, $status);
        }

        if (! is_array($content)) {
            return $this->ok(['data' => $content]);
        }

        $payload = $content['data'] ?? $content;
        if (is_array($payload)) {
            return $this->ok($payload);
        }

        return $this->ok(['data' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ok(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['ok' => true], $payload), $status);
    }

    protected function unavailable(string $message, int $crmStatus = 404): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'unavailable' => true,
            'message' => $message,
            'crm_status' => $crmStatus,
        ], 200);
    }
}
