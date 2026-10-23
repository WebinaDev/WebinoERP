<?php

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Hook fired by core:license-renewal-reminders (7/3/1 days before expiry). */
class LicenseExpiringSoon
{
    use Dispatchable;

    public function __construct(public int $licenseId, public int $daysLeft, public string $renewUrl) {}
}
