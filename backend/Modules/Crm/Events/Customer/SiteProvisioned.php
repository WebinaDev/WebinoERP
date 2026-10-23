<?php

namespace Modules\Crm\Events\Customer;

class SiteProvisioned extends CustomerActivityEvent
{
    public const MODULE = 'sitebuilder';

    public const TYPE = 'site.provisioned';

    public const SUBJECT = 'site_provision';

    public const TITLE = 'ایجاد سایت';
}
