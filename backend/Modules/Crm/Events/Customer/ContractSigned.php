<?php

namespace Modules\Crm\Events\Customer;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired when a customer contract is signed from the hub. Listeners (activity, onboarding automation) are Phase 2/4. */
class ContractSigned
{
    use Dispatchable;

    public function __construct(public int $accountId, public int $contractId, public ?int $byUserId = null, public ?int $documentId = null) {}
}
