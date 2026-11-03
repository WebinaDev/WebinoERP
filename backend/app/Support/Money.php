<?php

namespace App\Support;

use Modules\Core\Entities\SystemSetting;
use Modules\Crm\Services\CpqService;

/**
 * Single price handler for ERP admin + storefront display and gateway settlement.
 *
 * Storage / settlement base: integer IRR (rial). See {@see Currency}.
 * Display unit: SystemSetting `display_currency` (falls back to `accounting_currency`).
 */
final class Money
{
    public const SETTING_DISPLAY = 'display_currency';

    public const SETTING_SYMBOL = 'display_currency_symbol';

    public const SETTING_ACCOUNTING = 'accounting_currency';

    public const SETTING_ACCOUNTING_SYMBOL = 'accounting_currency_symbol';

    public const SETTING_DECIMALS = 'display_currency_decimals';

    public const SETTING_THOUSAND_SEP = 'display_thousand_separator';

    public const SETTING_DECIMAL_SEP = 'display_decimal_separator';

    /** Read the configured display unit (IRT by default). */
    public static function displayCurrency(): string
    {
        $raw = SystemSetting::get(self::SETTING_DISPLAY);
        if ($raw === null || $raw === '') {
            $raw = SystemSetting::get(self::SETTING_ACCOUNTING, 'IRT');
        }

        $code = Currency::normalize(is_string($raw) ? $raw : 'IRT', 'IRT');
        if (! in_array($code, Currency::DISPLAY_CODES, true)) {
            return 'IRT';
        }

        return $code;
    }

    public static function displaySymbol(): string
    {
        $currency = self::displayCurrency();
        $raw = SystemSetting::get(self::SETTING_SYMBOL);
        if ($raw === null || $raw === '') {
            $raw = SystemSetting::get(self::SETTING_ACCOUNTING_SYMBOL, 'default');
        }

        return self::normalizeSymbol($currency, is_string($raw) ? $raw : null);
    }

    public static function normalizeSymbol(string $currency, ?string $symbol): string
    {
        $currency = Currency::normalize($currency, 'IRT');
        $symbol = trim((string) $symbol);
        if (Currency::isForeign($currency)) {
            return Currency::symbol($currency);
        }
        $allowed = match ($currency) {
            'IRR' => ['rial-1', 'rial-2'],
            'IRHT' => ['default', 'toman-1', 'toman-2'],
            default => ['default', 'toman-1', 'toman-2'],
        };
        if (in_array($symbol, $allowed, true)) {
            return $symbol;
        }

        return $currency === 'IRR' ? 'rial-1' : 'default';
    }

    /**
     * Persist display (+ mirrored accounting) currency settings.
     *
     * @param  array{currency?:string,currency_symbol?:string,decimals?:int,thousand_separator?:string,decimal_separator?:string}  $input
     * @return array<string, mixed>
     */
    public static function saveSettings(array $input): array
    {
        $hasCurrency = array_key_exists('currency', $input) && $input['currency'] !== null && $input['currency'] !== '';
        $currency = $hasCurrency
            ? Currency::normalize((string) $input['currency'], 'IRT')
            : self::displayCurrency();
        if (! in_array($currency, Currency::DISPLAY_CODES, true)) {
            $currency = 'IRT';
        }

        if ($hasCurrency) {
            SystemSetting::set(self::SETTING_DISPLAY, $currency, 'pricing');
            SystemSetting::set(self::SETTING_ACCOUNTING, in_array($currency, ['IRR', 'IRT'], true) ? $currency : 'IRT', 'accounting');
        }

        $hasSymbol = array_key_exists('currency_symbol', $input) && $input['currency_symbol'] !== null && $input['currency_symbol'] !== '';
        if ($hasSymbol || $hasCurrency) {
            $symbol = self::normalizeSymbol(
                $currency,
                $hasSymbol ? (string) $input['currency_symbol'] : self::displaySymbol()
            );
            SystemSetting::set(self::SETTING_SYMBOL, $symbol, 'pricing');
            SystemSetting::set(self::SETTING_ACCOUNTING_SYMBOL, $symbol, 'accounting');
        }

        if (array_key_exists('decimals', $input) && $input['decimals'] !== null && $input['decimals'] !== '') {
            SystemSetting::set(self::SETTING_DECIMALS, (string) max(0, min(4, (int) $input['decimals'])), 'pricing');
        }
        if (array_key_exists('thousand_separator', $input) && $input['thousand_separator'] !== null) {
            SystemSetting::set(self::SETTING_THOUSAND_SEP, mb_substr((string) $input['thousand_separator'], 0, 2), 'pricing');
        }
        if (array_key_exists('decimal_separator', $input) && $input['decimal_separator'] !== null) {
            SystemSetting::set(self::SETTING_DECIMAL_SEP, mb_substr((string) $input['decimal_separator'], 0, 2), 'pricing');
        }

        return self::settingsPayload();
    }

    /** @return array<string, mixed> */
    public static function settingsPayload(): array
    {
        $currency = self::displayCurrency();
        $decimals = (int) (SystemSetting::get(self::SETTING_DECIMALS, (string) Currency::decimals($currency)) ?? Currency::decimals($currency));

        return [
            'currency' => $currency,
            'currency_symbol' => self::displaySymbol(),
            'label_fa' => Currency::label($currency, 'fa'),
            'label_en' => Currency::label($currency, 'en'),
            'base' => Currency::BASE,
            'settlement' => Currency::SETTLEMENT,
            'decimals' => $decimals,
            'thousand_separator' => (string) (SystemSetting::get(self::SETTING_THOUSAND_SEP, ',') ?? ','),
            'decimal_separator' => (string) (SystemSetting::get(self::SETTING_DECIMAL_SEP, '.') ?? '.'),
            'allowed' => Currency::DISPLAY_CODES,
        ];
    }

    /** Public storefront currency_display blob. */
    public static function storefrontDisplay(): array
    {
        $s = self::settingsPayload();

        return [
            'currency' => $s['currency'],
            'currency_symbol' => $s['currency_symbol'],
            'currency_position' => 'after',
            'thousand_separator' => $s['thousand_separator'],
            'decimal_separator' => $s['decimal_separator'],
            'price_decimals' => $s['decimals'],
            'base' => Currency::BASE,
            'settlement' => Currency::SETTLEMENT,
            'label' => $s['label_fa'],
        ];
    }

    /** Convert canonical IRR amount → display units. */
    public static function fromRial(float|int $rial, ?string $displayCurrency = null): float
    {
        $code = $displayCurrency ? Currency::normalize($displayCurrency, 'IRT') : self::displayCurrency();

        return Currency::fromRial($rial, $code, self::fxRialPerUnit($code));
    }

    /** Convert display / storage amount → IRR for gateways & DB. */
    public static function toRial(float|int $amount, ?string $fromCurrency = null): float
    {
        $code = $fromCurrency ? Currency::normalize($fromCurrency, 'IRT') : self::displayCurrency();

        return Currency::toRial($amount, $code, self::fxRialPerUnit($code));
    }

    public static function toRialInt(float|int $amount, ?string $fromCurrency = null): int
    {
        return (int) round(self::toRial($amount, $fromCurrency));
    }

    /**
     * Gateway settlement amount (IRR integer). Iranian PSPs require rials.
     */
    public static function gatewayRial(float|int $amount, ?string $fromCurrency = null): int
    {
        return max(0, Currency::toGatewayRial($amount, $fromCurrency ?? Currency::BASE, self::fxRialPerUnit($fromCurrency ?? Currency::BASE)));
    }

    /**
     * When the amount is already stored as IRR (ERP shop convention), pass through.
     * When callers pass display units, convert first.
     */
    public static function gatewayRialFromBase(float|int $rialAmount): int
    {
        return max(0, (int) round((float) $rialAmount));
    }

    public static function formatRial(float|int $rial, ?string $locale = 'fa', ?string $displayCurrency = null): string
    {
        $code = $displayCurrency ? Currency::normalize($displayCurrency, 'IRT') : self::displayCurrency();
        $amount = self::fromRial($rial, $code);
        $decimals = Currency::decimals($code);
        $rounded = $decimals > 0 ? round($amount, $decimals) : (float) round($amount);
        $formatted = self::formatNumber($rounded, $locale ?? 'fa', $decimals);

        return trim($formatted.' '.Currency::label($code, $locale ?? 'fa'));
    }

    public static function formatNumber(float|int $amount, string $locale = 'fa', int $decimals = 0): string
    {
        $thousand = (string) (SystemSetting::get(self::SETTING_THOUSAND_SEP, ',') ?? ',');
        $decimal = (string) (SystemSetting::get(self::SETTING_DECIMAL_SEP, '.') ?? '.');
        $n = number_format((float) $amount, $decimals, '.', '');
        [$int, $frac] = array_pad(explode('.', $n, 2), 2, null);
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousand, (string) $int) ?? (string) $int;
        $out = $frac !== null && $decimals > 0 ? $int.$decimal.$frac : $int;
        if (str_starts_with(strtolower($locale), 'fa')) {
            return self::toFaDigits($out);
        }

        return $out;
    }

    public static function fxRialPerUnit(?string $currency): ?float
    {
        $code = Currency::normalize($currency, 'IRT');
        if (! Currency::isForeign($code)) {
            return null;
        }
        $setting = SystemSetting::get('fx_rate_'.$code.'_IRR');
        if ($setting !== null && $setting !== '' && is_numeric($setting) && (float) $setting > 0) {
            return (float) $setting;
        }
        try {
            if (class_exists(CpqService::class)) {
                /** @var CpqService $cpq */
                $cpq = app(CpqService::class);
                $rate = $cpq->convert($code, 'IRR', 1.0);

                return $rate > 0 ? $rate : null;
            }
        } catch (\Throwable) {
            // fall through
        }

        return null;
    }

    private static function toFaDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
