<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreLicense;
use Modules\Integrations\Entities\ModirPayamakMessage;
use Modules\Integrations\Services\ModirPayamakManager;
use Modules\Integrations\Services\ModirPayamakSiteSmsService;

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
        'settings/site' => 'siteSettings',
        'templates' => 'templates',
        'templates/shortcodes' => 'templateShortcodes',
        'patterns/registry' => 'patternRegistry',
        'patterns/sync' => 'patternSync',
        'patterns/detach' => 'patternDetach',
        'auth/send-otp' => 'authSendOtp',
        'site/settings/sms' => 'siteSettings',
    ];

    public function __construct(
        private ModirPayamakManager $manager,
        private ModirPayamakCustomerController $customer,
        private ModirPayamakAdminController $admin,
        private ModirPayamakSiteSmsService $siteSms,
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
                if ($path === 'newsletter' || str_starts_with($path, 'newsletter/')) {
                    return $this->unavailable('Newsletter SMS is not available on ERP Edge; use Dashboard local newsletter.');
                }

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
            $body = $request->all();
            $input = is_array($body['settings'] ?? null) ? $body['settings'] : [];
            $settings = $this->siteSms->saveShopSettings($domain, $input);
            $templates = is_array($body['templates'] ?? null)
                ? $this->siteSms->saveTemplates($domain, $body['templates'])
                : $this->siteSms->listTemplates($domain);

            return $this->ok(['saved' => true, 'settings' => $settings, 'templates' => $templates]);
        }

        return $this->ok([
            'provider' => 'modirpayamak',
            'settings' => $this->siteSms->getShopSettings($domain),
            'event_keys' => $this->manager::ORDER_EVENTS,
            'templates' => $this->siteSms->listTemplates($domain),
            'shortcodes' => [],
            'registry' => $this->siteSms->listRegistry($domain),
        ]);
    }

    protected function siteSettings(Request $request, string $domain): JsonResponse
    {
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            $body = $request->all();
            $input = is_array($body['settings'] ?? null) ? $body['settings'] : $body;
            unset($input['domain'], $input['license_key'], $input['path'], $input['settings']);
            $settings = $this->siteSms->saveSiteSettings($domain, $input);

            return $this->ok(['saved' => true, 'settings' => $settings]);
        }

        return $this->ok([
            'settings' => $this->siteSms->getSiteSettings($domain),
            'unavailable' => false,
        ]);
    }

    protected function templates(Request $request, string $domain): JsonResponse
    {
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            $templates = $request->input('templates', []);
            if (! is_array($templates)) {
                $templates = [];
            }
            $saved = $this->siteSms->saveTemplates($domain, $templates);

            return $this->ok(['templates' => $saved]);
        }

        $scope = $request->query('scope');
        $eventKey = $request->query('event_key');

        return $this->ok([
            'templates' => $this->siteSms->listTemplates(
                $domain,
                is_string($scope) && $scope !== '' ? $scope : null,
                is_string($eventKey) && $eventKey !== '' ? $eventKey : null,
            ),
        ]);
    }

    protected function templateShortcodes(Request $request, string $domain): JsonResponse
    {
        return $this->ok([
            'shortcodes' => [
                ['key' => 'code', 'label' => 'OTP code', 'scope' => ModirPayamakSiteSmsService::SCOPE_SITE],
            ],
            'event_keys' => $this->manager::SITE_EVENTS,
        ]);
    }

    protected function patternRegistry(Request $request, string $domain): JsonResponse
    {
        return $this->ok(['registry' => $this->siteSms->listRegistry($domain)]);
    }

    protected function patternSync(Request $request, string $domain): JsonResponse
    {
        $scope = (string) $request->input('scope', '');
        $eventKey = (string) $request->input('event_key', '');
        $code = (string) ($request->input('pattern_code') ?? $request->input('ippanel_code') ?? '');
        $paramMap = $request->input('param_map', []);
        if (! is_array($paramMap)) {
            $paramMap = [];
        }

        if ($code !== '' && $request->boolean('bind_only')) {
            $result = $this->siteSms->bindPattern($domain, $scope, $eventKey, $code, $paramMap);
            if (empty($result['ok'])) {
                return response()->json([
                    'ok' => false,
                    'message' => (string) ($result['message'] ?? 'Pattern bind failed'),
                ], 422);
            }

            return $this->ok($result);
        }

        if ($code !== '') {
            $body = (string) $request->input('body', '');
            if ($body !== '') {
                $this->siteSms->saveTemplates($domain, [[
                    'scope' => $scope,
                    'event_key' => $eventKey,
                    'body' => $body,
                    'pattern_code' => $code,
                    'enabled' => true,
                ]]);
            }
            $result = $this->siteSms->bindPattern($domain, $scope, $eventKey, $code, $paramMap);
            if (empty($result['ok'])) {
                return response()->json([
                    'ok' => false,
                    'message' => (string) ($result['message'] ?? 'Pattern sync failed'),
                ], 422);
            }

            return $this->ok($result);
        }

        return response()->json(['ok' => false, 'message' => 'pattern_code is required'], 422);
    }

    protected function patternDetach(Request $request, string $domain): JsonResponse
    {
        $scope = (string) $request->input('scope', '');
        $eventKey = (string) $request->input('event_key', '');
        \Modules\Integrations\Entities\ModirPayamakPatternRegistry::query()
            ->where('domain', $domain)
            ->where('scope', $scope)
            ->where('event_key', $eventKey)
            ->delete();

        return $this->ok(['registry' => $this->siteSms->listRegistry($domain)]);
    }

    protected function authSendOtp(Request $request, string $domain): JsonResponse
    {
        $phone = (string) $request->input('phone', '');
        $purpose = (string) $request->input('purpose', 'login');
        $result = $this->siteSms->sendOtp($domain, $phone, $purpose);
        if (empty($result['ok'])) {
            return response()->json([
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'OTP send failed'),
            ], 422);
        }

        return $this->ok($result);
    }

    protected function forward(Request $request, string $path): JsonResponse
    {
        $segments = explode('/', $path);
        $resource = $segments[0] ?? '';

        $response = $path === 'reports/inbox' ? $this->forwardAdmin($request, $path) : match ($resource) {
            'account', 'packages', 'topup', 'send', 'reports', 'patterns', 'numbers', 'phonebooks', 'drafts' => $this->forwardCustomer($request, $path),
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
            'send/cancel-scheduled' => 'cancelScheduled',
            'reports/messages' => 'reportsMessages',
            'reports/outbox' => 'reportsOutbox',
            'patterns' => 'patterns',
            'numbers' => 'numbers',
            'phonebooks' => 'phonebooks',
            'drafts' => 'drafts',
            'drafts/groups' => 'draftGroups',
            default => null,
        };

        if ($method === null && str_starts_with($path, 'phonebooks/') && str_ends_with($path, '/contacts')) {
            if ($request->filled('phone') && ! $request->filled('number')) {
                $request->merge(['number' => $request->input('phone')]);
            }

            return $this->customer->phonebookContacts($request, (int) explode('/', $path)[1]);
        }

        if ($method === null && ($path === 'phonebooks/edge' || str_starts_with($path, 'phonebooks/edge/'))) {
            $sub = $path === 'phonebooks/edge' ? '' : substr($path, strlen('phonebooks/edge/'));

            return $this->customer->phonebooksEdge($request, $sub);
        }

        if ($method === null && str_starts_with($path, 'reports/outbox/')) {
            return $this->customer->reportOutboxDetail($request, (string) explode('/', $path)[2]);
        }

        if ($method === null && str_starts_with($path, 'reports/bulk-')) {
            $outboxId = substr($path, strlen('reports/bulk-'));

            return $this->customer->reportsBulk($request, $outboxId !== '' ? $outboxId : (string) $request->query('messages_outbox_id', ''));
        }

        if ($method === null && preg_match('#^drafts/([^/]+)$#', $path, $m)) {
            return $this->customer->draftShow($request, $m[1]);
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
            'secretaries/process' => $this->admin->secretariesProcess($request),
            'orders/notify' => $this->admin->ordersNotify($request, false),
            'orders/test-notify' => $this->admin->ordersNotify($request, true),
            'reports/inbox' => $this->admin->reportsInbox($request),
            default => abort(404, "Unknown admin path: {$path}"),
        };
    }

    protected function isUnavailablePath(string $path): bool
    {
        // Newsletter has no Edge product — honest unavailable (Dashboard local newsletter covers tenants).
        if ($path === 'newsletter' || str_starts_with($path, 'newsletter/')) {
            return true;
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
            'message' => $this->publicSmsErrorMessage($message),
            'crm_status' => $crmStatus,
        ], 200);
    }

    protected function publicSmsErrorMessage(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return 'SMS service is temporarily unavailable.';
        }
        if (preg_match('/SQLSTATE|Undefined table|relation\s+"|modirpayamak_|ippanel|stack\s+trace/i', $trimmed)) {
            return 'SMS service is temporarily unavailable.';
        }

        return $trimmed;
    }
}
