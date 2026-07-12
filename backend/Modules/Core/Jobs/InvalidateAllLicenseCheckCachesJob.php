<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;

class InvalidateAllLicenseCheckCachesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        CoreLicense::query()->select(['domain', 'license_key'])->orderBy('id')->chunk(100, function ($rows): void {
            foreach ($rows as $license) {
                CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);
            }
        });
    }
}
