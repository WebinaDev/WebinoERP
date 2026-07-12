<?php

namespace Modules\Core\Support;

/**
 * Parity helpers for webinocrm class-white-label.php hex_to_rgb() / darken_color().
 */
final class ColorUtil
{
    public static function normalizeHex(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (! preg_match('/^[0-9A-Fa-f]{3,6}$/', $hex)) {
            return '#000000';
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.strtolower($hex);
    }

    /**
     * Returns `r, g, b` string suitable for `rgba(r, g, b, a)` usage.
     */
    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim(self::normalizeHex($hex), '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    public static function darken(string $hex, int $percent): string
    {
        $hex = ltrim(self::normalizeHex($hex), '#');
        $r = max(0, min(255, (int) (hexdec(substr($hex, 0, 2)) * (100 - $percent) / 100)));
        $g = max(0, min(255, (int) (hexdec(substr($hex, 2, 2)) * (100 - $percent) / 100)));
        $b = max(0, min(255, (int) (hexdec(substr($hex, 4, 2)) * (100 - $percent) / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
