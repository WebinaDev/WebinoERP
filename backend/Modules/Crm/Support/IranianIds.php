<?php

namespace Modules\Crm\Support;

/**
 * Validators for Iranian identifiers. All accept Persian/Arabic digits and
 * common separators; use normalize() before storing.
 */
final class IranianIds
{
    public static function normalize(?string $v): string
    {
        $v = strtr((string) $v, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return strtoupper((string) preg_replace('/[\s\-_.\/]/u', '', $v));
    }

    /** کد ملی اشخاص حقیقی (۱۰ رقم با رقم کنترل). */
    public static function isNationalCode(?string $v): bool
    {
        $v = self::normalize($v);
        if (! preg_match('/^\d{10}$/', $v) || preg_match('/^(\d)\1{9}$/', $v)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $v[$i] * (10 - $i);
        }
        $r = $sum % 11;
        $check = (int) $v[9];

        return $r < 2 ? $check === $r : $check === 11 - $r;
    }

    /** شناسه ملی اشخاص حقوقی (۱۱ رقم با رقم کنترل). */
    public static function isLegalId(?string $v): bool
    {
        $v = self::normalize($v);
        if (! preg_match('/^\d{11}$/', $v) || preg_match('/^(\d)\1{10}$/', $v)) {
            return false;
        }
        $coef = [29, 27, 23, 19, 17, 29, 27, 23, 19, 17];
        $dec = (int) $v[9] + 2;
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $v[$i] + $dec) * $coef[$i];
        }
        $r = $sum % 11;
        if ($r === 10) {
            $r = 0;
        }

        return $r === (int) $v[10];
    }

    /** شماره شبا: IR + ۲۴ رقم با بررسی mod-97. */
    public static function isIban(?string $v): bool
    {
        $v = self::normalize($v);
        if (preg_match('/^\d{24}$/', $v)) {
            $v = 'IR'.$v;
        }
        if (! preg_match('/^IR\d{24}$/', $v)) {
            return false;
        }
        $num = substr($v, 4).'1827'.substr($v, 2, 2); // I=18, R=27
        $mod = 0;
        foreach (str_split($num, 7) as $chunk) {
            $mod = (int) (($mod.$chunk) % 97);
        }

        return $mod === 1;
    }

    public static function normalizeIban(?string $v): ?string
    {
        $v = self::normalize($v);
        if ($v === '') {
            return null;
        }

        return preg_match('/^\d{24}$/', $v) ? 'IR'.$v : $v;
    }

    public static function maskIban(?string $v): ?string
    {
        if (! $v) {
            return null;
        }

        return substr($v, 0, 4).str_repeat('•', max(0, strlen($v) - 8)).substr($v, -4);
    }
}
