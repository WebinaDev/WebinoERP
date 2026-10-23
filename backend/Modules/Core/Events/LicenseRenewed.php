<?php

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LicenseRenewed
{
    use Dispatchable;

    public function __construct(public int $licenseId, public int $renewalId, public ?int $accountId, public string $newExpiresAt) {}
}
