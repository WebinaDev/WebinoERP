<?php

namespace Modules\Integrations\Services\Payments;

use Modules\Integrations\Entities\IntegrationSetting;

class GatewayConfigStore
{
    public const INTEGRATION = 'payment';

    public const KEY = 'gateways';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        $catalog = config('payment_gateways.gateways', []);

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->catalog());
    }

    /**
     * Raw saved map (secrets decrypted by IntegrationSetting).
     *
     * @return array<string, array<string, mixed>>
     */
    public function saved(): array
    {
        $saved = IntegrationSetting::getJson(self::INTEGRATION, self::KEY, []);

        return is_array($saved) ? $saved : [];
    }

    /**
     * Effective runtime config for a driver. Secrets prefer the saved value, then env.
     *
     * @return array<string, mixed>
     */
    public function for(string $code): array
    {
        $spec = $this->catalog()[$code] ?? null;
        if (! is_array($spec)) {
            throw new PaymentException('درگاه ناشناخته است.', 'unknown_gateway');
        }
        $savedAll = $this->saved();
        $row = is_array($savedAll[$code] ?? null) ? $savedAll[$code] : [];
        $defaults = is_array($spec['defaults'] ?? null) ? $spec['defaults'] : [];
        $envMap = is_array($spec['env_fallback'] ?? null) ? $spec['env_fallback'] : [];

        $merged = array_merge($defaults, $row);
        foreach ($envMap as $field => $fromEnv) {
            if ($fromEnv === null || $fromEnv === '') {
                continue;
            }
            if (in_array($field, ['sandbox', 'enabled'], true)) {
                if (! array_key_exists($field, $row)) {
                    $merged[$field] = filter_var($fromEnv, FILTER_VALIDATE_BOOLEAN);
                }
                continue;
            }
            $current = trim((string) ($merged[$field] ?? ''));
            if ($current === '') {
                $merged[$field] = is_string($fromEnv) ? $fromEnv : (string) $fromEnv;
            }
        }

        if (! array_key_exists('enabled', $row)) {
            $merged['enabled'] = filter_var($envMap['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $merged['code'] = $code;
        $merged['supports'] = $spec['supports'] ?? [];
        $merged['label_fa'] = $spec['label_fa'] ?? $code;
        $merged['urls'] = $spec['urls'] ?? [];
        $merged['sandbox'] = (bool) ($merged['sandbox'] ?? true);
        $merged['enabled'] = (bool) ($merged['enabled'] ?? false);
        $merged['cash_enabled'] = (bool) ($merged['cash_enabled'] ?? false);
        $merged['installment_enabled'] = (bool) ($merged['installment_enabled'] ?? false);
        $merged['fee_percent'] = (float) ($merged['fee_percent'] ?? 0);
        $merged['apply_fee_on_cash'] = (bool) ($merged['apply_fee_on_cash'] ?? false);
        $merged['local_simulation'] = $this->usesLocalSimulation($code, $merged);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function usesLocalSimulation(string $code, array $config): bool
    {
        $sandbox = (bool) ($config['sandbox'] ?? true);
        if (! $sandbox) {
            return false;
        }
        if ($code === 'zarinpal') {
            $merchant = trim((string) ($config['merchant_id'] ?? ''));

            return $merchant === '' || $merchant === 'sandbox' || strlen($merchant) < 36;
        }
        if ($code === 'digipay') {
            return $this->missingLiveCredentials($code, $config);
        }

        $base = trim((string) ($config['base_url'] ?? ''));
        if ($base !== '' && ! $this->missingLiveCredentials($code, $config)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function missingLiveCredentials(string $code, array $config): bool
    {
        $spec = $this->catalog()[$code] ?? [];
        $required = is_array($spec['required_live'] ?? null) ? $spec['required_live'] : [];
        foreach ($required as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicList(): array
    {
        $out = [];
        foreach ($this->codes() as $code) {
            $out[] = $this->publicOne($code);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicOne(string $code): array
    {
        $spec = $this->catalog()[$code] ?? [];
        $config = $this->for($code);
        $fields = [];
        foreach ($spec['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $value = trim((string) ($config[$key] ?? ''));
            $fields[] = [
                'key' => $key,
                'secret' => (bool) ($field['secret'] ?? true),
                'label_fa' => (string) ($field['label_fa'] ?? $key),
                'label_en' => (string) ($field['label_en'] ?? $key),
                'configured' => $value !== '',
                'masked' => $value === '' ? null : $this->mask($value),
            ];
        }

        return [
            'code' => $code,
            'label_fa' => (string) ($spec['label_fa'] ?? $code),
            'label_en' => (string) ($spec['label_en'] ?? $code),
            'supports' => array_values($spec['supports'] ?? []),
            'enabled' => (bool) $config['enabled'],
            'sandbox' => (bool) $config['sandbox'],
            'cash_enabled' => (bool) $config['cash_enabled'],
            'installment_enabled' => (bool) $config['installment_enabled'],
            'fee_percent' => (float) $config['fee_percent'],
            'apply_fee_on_cash' => (bool) $config['apply_fee_on_cash'],
            'callback_base_url' => (string) ($config['callback_base_url'] ?? ''),
            'base_url' => (string) ($config['base_url'] ?? ''),
            'ticket_type_cash' => (int) ($config['ticket_type_cash'] ?? 0),
            'ticket_type_installment' => (int) ($config['ticket_type_installment'] ?? 13),
            'local_simulation' => (bool) $config['local_simulation'],
            'notes_fa' => (string) ($spec['notes_fa'] ?? ''),
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $input keyed by gateway code
     * @return list<array<string, mixed>>
     */
    public function saveAll(array $input): array
    {
        $errors = [];
        $saved = $this->saved();
        $next = $saved;

        foreach ($this->codes() as $code) {
            if (! array_key_exists($code, $input) || ! is_array($input[$code])) {
                continue;
            }
            $rowErrors = $this->validateRow($code, $input[$code], $saved[$code] ?? []);
            foreach ($rowErrors as $key => $message) {
                $errors[$code.'.'.$key] = $message;
            }
            if ($rowErrors === []) {
                $next[$code] = $this->mergeRow($code, $input[$code], is_array($saved[$code] ?? null) ? $saved[$code] : []);
            }
        }

        if ($errors !== []) {
            throw new PaymentException('تنظیمات درگاه معتبر نیست.', 'validation', 422, $errors);
        }

        IntegrationSetting::putJson(self::INTEGRATION, self::KEY, $next);

        return $this->publicList();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $previous
     * @return array<string, string>
     */
    public function validateRow(string $code, array $input, array $previous = []): array
    {
        $spec = $this->catalog()[$code] ?? null;
        if (! is_array($spec)) {
            return ['code' => 'درگاه ناشناخته است.'];
        }
        $errors = [];
        $supports = $spec['supports'] ?? [];
        $enabled = (bool) ($input['enabled'] ?? false);
        $cash = (bool) ($input['cash_enabled'] ?? false);
        $installment = (bool) ($input['installment_enabled'] ?? false);
        $sandbox = (bool) ($input['sandbox'] ?? true);
        $fee = $input['fee_percent'] ?? 0;
        if (! is_numeric($fee) || (float) $fee < 0 || (float) $fee > 100) {
            $errors['fee_percent'] = 'کارمزد باید عددی بین ۰ و ۱۰۰ باشد.';
        }
        if ($cash && ! in_array('cash', $supports, true)) {
            $errors['cash_enabled'] = 'این درگاه پرداخت نقدی ندارد.';
        }
        if ($installment && ! in_array('installment', $supports, true)) {
            $errors['installment_enabled'] = 'این درگاه پرداخت اقساطی ندارد.';
        }
        if ($enabled && ! $cash && ! $installment) {
            $errors['mode'] = 'برای درگاه فعال حداقل یکی از حالت‌های نقدی یا اقساطی را انتخاب کنید.';
        }
        foreach (['callback_base_url', 'base_url'] as $urlKey) {
            $url = trim((string) ($input[$urlKey] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[$urlKey] = 'آدرس وارد شده معتبر نیست.';
            }
        }
        if ($enabled && ! $sandbox) {
            $merged = $this->mergeRow($code, $input, $previous);
            foreach ($spec['required_live'] ?? [] as $field) {
                if (trim((string) ($merged[$field] ?? '')) === '') {
                    $errors[$field] = 'در حالت عملیاتی این مقدار الزامی است.';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function mergeRow(string $code, array $input, array $previous): array
    {
        $spec = $this->catalog()[$code] ?? [];
        $defaults = is_array($spec['defaults'] ?? null) ? $spec['defaults'] : [];
        $row = array_merge($defaults, $previous);
        $row['enabled'] = (bool) ($input['enabled'] ?? false);
        $row['sandbox'] = (bool) ($input['sandbox'] ?? true);
        $row['cash_enabled'] = (bool) ($input['cash_enabled'] ?? false);
        $row['installment_enabled'] = (bool) ($input['installment_enabled'] ?? false);
        $row['apply_fee_on_cash'] = (bool) ($input['apply_fee_on_cash'] ?? false);
        $row['fee_percent'] = round((float) ($input['fee_percent'] ?? 0), 3);
        $row['callback_base_url'] = trim((string) ($input['callback_base_url'] ?? ($previous['callback_base_url'] ?? '')));
        $row['base_url'] = trim((string) ($input['base_url'] ?? ($previous['base_url'] ?? '')));
        if ($code === 'digipay') {
            $row['ticket_type_cash'] = (int) ($input['ticket_type_cash'] ?? ($previous['ticket_type_cash'] ?? 0));
            $row['ticket_type_installment'] = (int) ($input['ticket_type_installment'] ?? ($previous['ticket_type_installment'] ?? 13));
        }
        foreach ($spec['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) $field['key'];
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = trim((string) $input[$key]);
            if ($value === '' || $value === '********') {
                continue;
            }
            $row[$key] = $value;
        }

        return $row;
    }

    /**
     * Enabled gateways that accept the given mode.
     *
     * @return list<array<string, mixed>>
     */
    public function optionsFor(?string $mode = null): array
    {
        $out = [];
        foreach ($this->codes() as $code) {
            $config = $this->for($code);
            if (! $config['enabled']) {
                continue;
            }
            $modes = [];
            if ($config['cash_enabled'] && in_array('cash', $config['supports'], true)) {
                $modes[] = 'cash';
            }
            if ($config['installment_enabled'] && in_array('installment', $config['supports'], true)) {
                $modes[] = 'installment';
            }
            if ($modes === []) {
                continue;
            }
            if ($mode !== null && $mode !== '' && ! in_array($mode, $modes, true)) {
                continue;
            }
            $public = $this->publicOne($code);
            unset($public['fields']);
            $public['modes'] = $modes;
            $out[] = $public;
        }

        return $out;
    }

    public function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return '••••';
        }

        return '••••'.substr($value, -4);
    }
}
