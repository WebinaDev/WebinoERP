<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\Cache;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakDomainNumber;
use Modules\Integrations\Entities\ModirPayamakPatternRegistry;

/**
 * Per-domain site SMS settings, templates, pattern bind, and OTP test send.
 * Mirrors WebinaCRM Sms_Settings / Template / Pattern_Sync / Auth services.
 */
class ModirPayamakSiteSmsService
{
    public const SCOPE_SITE = 'site';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private ModirPayamakManager $manager,
        private ModirPayamakEdgeClient $edge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaultSiteSettings(): array
    {
        return [
            'enabled' => true,
            'sender_line_service' => '',
            'sender_line_dedicated' => '',
            'otp_login_enabled' => true,
            'otp_register_enabled' => true,
            'otp_expiry_minutes' => 5,
            'otp_max_attempts' => 3,
            'otp_length' => 6,
            'otp_login_template' => 'Code: {code}',
            'otp_register_template' => 'Registration code: {code}',
            'use_pattern_for_otp' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSiteSettings(string $domain): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $stored = IntegrationSetting::getJson('modirpayamak', $this->settingsKey($domain), []);
        $settings = array_merge($this->defaultSiteSettings(), is_array($stored) ? $stored : []);
        $settings = $this->enrichSenderLines($domain, $settings);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function saveSiteSettings(string $domain, array $input): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $raw = IntegrationSetting::getJson('modirpayamak', $this->settingsKey($domain), []);
        $out = array_merge($this->defaultSiteSettings(), is_array($raw) ? $raw : []);

        foreach (['enabled', 'otp_login_enabled', 'otp_register_enabled', 'use_pattern_for_otp'] as $k) {
            if (array_key_exists($k, $input)) {
                $out[$k] = (bool) $input[$k];
            }
        }
        foreach (['sender_line_service', 'sender_line_dedicated', 'otp_login_template', 'otp_register_template'] as $k) {
            if (array_key_exists($k, $input)) {
                $out[$k] = (string) $input[$k];
            }
        }
        if (array_key_exists('otp_expiry_minutes', $input)) {
            $out['otp_expiry_minutes'] = max(1, min(15, (int) $input['otp_expiry_minutes']));
        }
        if (array_key_exists('otp_length', $input)) {
            $out['otp_length'] = max(4, min(8, (int) $input['otp_length']));
        }
        if (array_key_exists('otp_max_attempts', $input)) {
            $out['otp_max_attempts'] = max(1, min(20, (int) $input['otp_max_attempts']));
        }

        IntegrationSetting::putJson('modirpayamak', $this->settingsKey($domain), $out);

        return $this->getSiteSettings($domain);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShopSettings(string $domain): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $stored = IntegrationSetting::getJson('modirpayamak', $this->shopSettingsKey($domain), []);

        return array_merge(['enabled' => true, 'events' => []], is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function saveShopSettings(string $domain, array $input): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $out = $this->getShopSettings($domain);

        foreach ($input as $k => $v) {
            if (is_string($k) && ! in_array($k, ['domain', 'license_key', 'path', 'templates'], true)) {
                $out[$k] = $v;
            }
        }
        $out['enabled'] = (bool) ($out['enabled'] ?? true);

        IntegrationSetting::putJson('modirpayamak', $this->shopSettingsKey($domain), $out);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTemplates(string $domain, ?string $scope = null, ?string $eventKey = null): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $rows = IntegrationSetting::getJson('modirpayamak', $this->templatesKey($domain), []);
        if (! is_array($rows)) {
            $rows = [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($scope && ($row['scope'] ?? '') !== $scope) {
                continue;
            }
            if ($eventKey && ($row['event_key'] ?? '') !== $eventKey) {
                continue;
            }
            $out[] = $row;
        }

        return array_values($out);
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @return list<array<string, mixed>>
     */
    public function saveTemplates(string $domain, array $templates): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $existing = IntegrationSetting::getJson('modirpayamak', $this->templatesKey($domain), []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $byKey = [];
        foreach ($existing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $k = ($row['scope'] ?? '').'|'.($row['event_key'] ?? '');
            if ($k !== '|') {
                $byKey[$k] = $row;
            }
        }

        foreach ($templates as $row) {
            if (! is_array($row)) {
                continue;
            }
            $scope = (string) ($row['scope'] ?? '');
            $eventKey = (string) ($row['event_key'] ?? '');
            if ($scope === '' || $eventKey === '') {
                continue;
            }
            $k = $scope.'|'.$eventKey;
            $prev = $byKey[$k] ?? [];
            $byKey[$k] = [
                'scope' => $scope,
                'event_key' => $eventKey,
                'body' => (string) ($row['body'] ?? $prev['body'] ?? ''),
                'pattern_code' => array_key_exists('pattern_code', $row)
                    ? ($row['pattern_code'] !== null && $row['pattern_code'] !== '' ? (string) $row['pattern_code'] : null)
                    : ($prev['pattern_code'] ?? null),
                'param_map' => array_key_exists('param_map', $row) ? $row['param_map'] : ($prev['param_map'] ?? null),
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : (bool) ($prev['enabled'] ?? true),
            ];
        }

        $saved = array_values($byKey);
        IntegrationSetting::putJson('modirpayamak', $this->templatesKey($domain), $saved);

        return $saved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRegistry(string $domain): array
    {
        $domain = $this->manager->normalizeDomain($domain);

        return ModirPayamakPatternRegistry::query()
            ->where('domain', $domain)
            ->orderBy('scope')
            ->orderBy('event_key')
            ->get()
            ->map(fn (ModirPayamakPatternRegistry $r) => [
                'scope' => $r->scope,
                'event_key' => $r->event_key,
                'ippanel_code' => $r->ippanel_code,
                'sync_status' => $r->sync_status,
                'last_error' => $r->last_error,
                'param_map' => $r->param_map,
            ])
            ->all();
    }

    /**
     * Bind an existing IPPanel pattern code to a site/shop event.
     *
     * @param  array<string, string>  $paramMap
     * @return array{ok: bool, ippanel_code: string, sync_status: string, param_map?: mixed, message?: string}
     */
    public function bindPattern(string $domain, string $scope, string $eventKey, string $code, array $paramMap = []): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $scope = trim($scope);
        $eventKey = trim($eventKey);
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'ippanel_code' => '', 'sync_status' => self::STATUS_FAILED, 'message' => 'Pattern code is required.'];
        }

        $edge = $this->edge->getPattern($code);
        if (! $edge['ok'] && ! env('MODIRPAYAMAK_MOCK', false)) {
            $this->upsertRegistry($domain, $scope, $eventKey, $code, self::STATUS_FAILED, (string) ($edge['message'] ?? 'Pattern not found'), $paramMap);

            return [
                'ok' => false,
                'ippanel_code' => $code,
                'sync_status' => self::STATUS_FAILED,
                'message' => (string) ($edge['message'] ?: 'Pattern not found on IPPanel.'),
            ];
        }

        $bodyFromEdge = '';
        if (is_array($edge['data'] ?? null) && ! empty($edge['data']['pattern_message'])) {
            $bodyFromEdge = (string) preg_replace('/%([a-zA-Z0-9_]+)%/', '{$1}', (string) $edge['data']['pattern_message']);
        }

        $this->saveTemplates($domain, [[
            'scope' => $scope,
            'event_key' => $eventKey,
            'body' => $bodyFromEdge !== '' ? $bodyFromEdge : '{code}',
            'pattern_code' => $code,
            'enabled' => true,
            'param_map' => $paramMap ?: null,
        ]]);

        $approval = '';
        if (is_array($edge['data'] ?? null)) {
            $approval = (string) ($edge['data']['status'] ?? $edge['data']['approval'] ?? $edge['data']['state'] ?? $edge['data']['pattern_status'] ?? '');
        }
        $synced = true;
        if ($approval !== '' && preg_match('/reject|fail|deny|pending|wait/i', $approval) && ! preg_match('/approv|active|ok|accept|confirm/i', $approval)) {
            $synced = false;
        }
        $status = $synced ? self::STATUS_SYNCED : self::STATUS_PENDING;
        $this->upsertRegistry($domain, $scope, $eventKey, $code, $status, $synced ? '' : $approval, $paramMap);

        return [
            'ok' => true,
            'ippanel_code' => $code,
            'sync_status' => $status,
            'param_map' => $paramMap ?: null,
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function sendOtp(string $domain, string $phone, string $purpose = 'login'): array
    {
        $domain = $this->manager->normalizeDomain($domain);
        $phone = $this->normalizePhone($phone);
        $purpose = $purpose === 'register' ? 'register' : 'login';

        if ($phone === '') {
            return ['ok' => false, 'message' => 'Phone is required.'];
        }

        $settings = $this->getSiteSettings($domain);
        if (empty($settings['enabled'])) {
            return ['ok' => false, 'message' => 'Site SMS is disabled.'];
        }
        if ($purpose === 'register' && empty($settings['otp_register_enabled'])) {
            return ['ok' => false, 'message' => 'Registration OTP is disabled.'];
        }
        if ($purpose === 'login' && empty($settings['otp_login_enabled'])) {
            return ['ok' => false, 'message' => 'Login OTP is disabled.'];
        }

        $length = max(4, min(8, (int) ($settings['otp_length'] ?? 6)));
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('9', $length, '9');
        $code = (string) random_int($min, $max);
        $expiry = max(1, (int) ($settings['otp_expiry_minutes'] ?? 5)) * 60;
        Cache::put($this->otpCacheKey($domain, $phone, $purpose), ['code' => $code, 'attempts' => 0], $expiry);

        $templateKey = $purpose === 'register' ? 'otp_register_template' : 'otp_login_template';
        $template = (string) ($settings[$templateKey] ?? $this->defaultSiteSettings()[$templateKey]);
        $from = $this->resolveFromNumber($domain, $settings);
        $usePattern = ! empty($settings['use_pattern_for_otp']);

        if ($usePattern) {
            $eventKey = $purpose === 'register' ? 'otp_register' : 'otp_login';
            $row = ModirPayamakPatternRegistry::query()
                ->where('domain', $domain)
                ->where('scope', self::SCOPE_SITE)
                ->where('event_key', $eventKey)
                ->first();
            $patternCode = trim((string) ($row?->ippanel_code ?? ''));
            if ($patternCode === '' || ($row?->sync_status === self::STATUS_FAILED)) {
                Cache::forget($this->otpCacheKey($domain, $phone, $purpose));

                return ['ok' => false, 'message' => 'OTP pattern is not ready.'];
            }
            $result = $this->edge->sendPattern($from, $patternCode, [$phone], ['code' => $code]);
        } else {
            $message = str_replace('{code}', $code, $template);
            $result = $this->edge->sendWebservice($from, $message, [$phone]);
        }

        if (! $result['ok'] && ! env('MODIRPAYAMAK_MOCK', false)) {
            Cache::forget($this->otpCacheKey($domain, $phone, $purpose));

            return ['ok' => false, 'message' => (string) ($result['message'] ?: 'Send failed')];
        }

        try {
            $this->manager->debit($domain, $this->manager->pricePerUnit(), 'otp', null, [
                'phone' => $phone,
                'purpose' => $purpose,
            ]);
        } catch (\Throwable) {
            // Balance debit is best-effort for test OTP; send already succeeded.
        }

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function enrichSenderLines(string $domain, array $settings): array
    {
        if (! Schema::hasTable('modirpayamak_domain_numbers')) {
            return $settings;
        }

        $service = ModirPayamakDomainNumber::query()
            ->where('domain', $domain)
            ->where('role', ModirPayamakManager::ROLE_SERVICE)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('number');
        $dedicated = ModirPayamakDomainNumber::query()
            ->where('domain', $domain)
            ->whereIn('role', [ModirPayamakManager::ROLE_PERSONAL, 'marketing'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('number');

        if ($service) {
            $settings['sender_line_service'] = (string) $service;
        }
        if ($dedicated) {
            $settings['sender_line_dedicated'] = (string) $dedicated;
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function resolveFromNumber(string $domain, array $settings): string
    {
        $line = trim((string) ($settings['sender_line_service'] ?? ''));
        if ($line !== '') {
            return $line;
        }
        $account = $this->manager->getOrCreateAccount($domain);

        return $account->default_from ?: $this->edge->defaultFrom();
    }

    /**
     * @param  array<string, string>  $paramMap
     */
    protected function upsertRegistry(
        string $domain,
        string $scope,
        string $eventKey,
        string $code,
        string $status,
        string $lastError = '',
        array $paramMap = [],
    ): void {
        ModirPayamakPatternRegistry::query()->updateOrCreate(
            [
                'domain' => $domain,
                'scope' => $scope,
                'event_key' => $eventKey,
            ],
            [
                'ippanel_code' => $code,
                'sync_status' => $status,
                'last_error' => $lastError !== '' ? $lastError : null,
                'param_map' => $paramMap ?: null,
                'submitted_at' => now(),
            ]
        );
    }

    protected function settingsKey(string $domain): string
    {
        return 'domain.'.$domain.'.site_settings';
    }

    protected function shopSettingsKey(string $domain): string
    {
        return 'domain.'.$domain.'.shop_settings';
    }

    protected function templatesKey(string $domain): string
    {
        return 'domain.'.$domain.'.templates';
    }

    protected function otpCacheKey(string $domain, string $phone, string $purpose): string
    {
        return 'modirpayamak:otp:'.$domain.':'.$purpose.':'.$phone;
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}
