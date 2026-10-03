<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class ModuleMonitor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function hook(string $module, string $event, array $context = []): void
    {
        Log::info('webino.'.$module.'.'.$event, $context);
    }
}
