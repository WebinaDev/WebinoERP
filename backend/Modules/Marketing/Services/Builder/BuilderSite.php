<?php

namespace Modules\Marketing\Services\Builder;

/**
 * Dashboard scopes builder rows by tenant_id. The ERP company site is a
 * single storefront, so every row uses this constant instead of a tenants FK.
 */
class BuilderSite
{
    public const ID = 1;
}
