<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integrations\Entities\IntegrationSetting;

class WebinoIntegrationsValidateCommand extends Command
{
    protected $signature = 'webino:integrations:validate {--strict : Fail on warnings in any environment}';

    protected $description = 'Validate SMS and Bale integration configuration for production readiness';

    public function handle(): int
    {
        $errors = [];
        $warnings = [];
        $isProd = app()->environment('production');

        $smsSettings = IntegrationSetting::getJson('sms', 'settings', []);
        $provider = (string) ($smsSettings['provider'] ?? config('integrations.sms.default', 'stub'));

        if ($isProd && in_array($provider, ['log', 'stub', 'disabled'], true)) {
            $errors[] = "SMS provider \"{$provider}\" is not allowed in production (use melipayamak or parsgreen).";
        }

        if ($provider === 'melipayamak') {
            $username = (string) ($smsSettings['username'] ?? env('MELIPAYAMAK_USERNAME', ''));
            $password = (string) ($smsSettings['password'] ?? env('MELIPAYAMAK_PASSWORD', ''));
            if ($username === '' || $password === '') {
                ($isProd ? $errors : $warnings)[] = 'Melipayamak requires username and password (IntegrationSetting or MELIPAYAMAK_* env).';
            }
        }

        if ($provider === 'parsgreen') {
            $apiKey = (string) ($smsSettings['api_key'] ?? env('PARSGREEN_API_KEY', ''));
            if ($apiKey === '') {
                ($isProd ? $errors : $warnings)[] = 'ParsGreen requires api_key (IntegrationSetting or PARSGREEN_API_KEY env).';
            }
        }

        $baleSettings = IntegrationSetting::getJson('bale', 'settings', []);
        $baleToken = trim((string) ($baleSettings['bot_token'] ?? config('integrations.bale.token', '')));
        $baleActive = $baleToken !== ''
            || ($baleSettings['enable_menu_features'] ?? '0') === '1'
            || ($baleSettings['enable_menu_support'] ?? '0') === '1';

        if ($isProd && $baleActive && $baleToken === '') {
            $errors[] = 'Bale bot is enabled but bot_token / BALE_BOT_TOKEN is missing.';
        }

        if ($baleToken !== '' && trim((string) config('integrations.bale.webhook_secret', '')) === ''
            && trim((string) ($baleSettings['webhook_secret'] ?? '')) === '') {
            $warnings[] = 'Bale webhook_secret is empty — consider setting BALE_WEBHOOK_SECRET for webhook verification.';
        }

        foreach ($warnings as $msg) {
            $this->warn('⚠ '.$msg);
        }
        foreach ($errors as $msg) {
            $this->error('✗ '.$msg);
        }

        if ($errors === [] && $warnings === []) {
            $this->info('✓ Integration configuration looks good.');
        } elseif ($errors === []) {
            $this->info('✓ No blocking errors (see warnings above).');
        }

        if ($errors !== [] || ($this->option('strict') && $warnings !== [])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
