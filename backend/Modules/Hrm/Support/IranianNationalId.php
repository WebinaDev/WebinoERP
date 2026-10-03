<?php

namespace Modules\Hrm\Support;

/** Iranian national ID (کد ملی): 10 digits plus the standard check digit. */
class IranianNationalId
{
    public static function normalize(string $value): string
    {
        $map = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];
        $value = strtr(trim($value), $map);

        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $code = self::normalize($value);
        if (! preg_match('/^\d{10}$/', $code)) {
            return false;
        }
        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $rem = $sum % 11;
        $check = (int) $code[9];

        return $rem < 2 ? $check === $rem : $check === (11 - $rem);
    }
}
