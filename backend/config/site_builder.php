<?php

return [
    // Tenant site-status contract (GET/PUT /api/v1/provision/site-status)
    'site_status_timeout' => (int) env('SITE_STATUS_TIMEOUT', 10),
    // Shown on the tenant "suspended" page (renew / contact buttons). Leave empty to let the site use its defaults.
    'site_status_renew_url' => env('SITE_STATUS_RENEW_URL', ''),
    'site_status_support_url' => env('SITE_STATUS_SUPPORT_URL', 'https://webina.dev/dashboard/tickets'),
];
