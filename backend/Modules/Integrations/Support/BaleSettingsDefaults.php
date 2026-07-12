<?php

namespace Modules\Integrations\Support;

use Modules\Integrations\Support\BaleDefaults\CoreDefaults;
use Modules\Integrations\Support\BaleDefaults\StructuredDefaults;
use Modules\Integrations\Support\BaleDefaults\TextDefaults;

/**
 * پیش‌فرض‌های تنظیمات ربات بله کسب‌وکار؛ منبع تجمیع‌شده از چند کلاس کوچک‌تر.
 */
class BaleSettingsDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_merge(
            CoreDefaults::get(),
            TextDefaults::get(),
            StructuredDefaults::get(),
        );
    }
}
