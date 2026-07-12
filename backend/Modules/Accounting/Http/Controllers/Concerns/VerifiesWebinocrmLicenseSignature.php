<?php

namespace Modules\Accounting\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait VerifiesWebinocrmLicenseSignature
{
    protected function licenseHmacSecret(): string
    {
        return (string) (config('app.webinocrm_license_hmac_secret') ?? '');
    }

    protected function verifyLicenseRequest(Request $request): bool
    {
        $secret = $this->licenseHmacSecret();
        if ($secret === '') {
            return true;
        }
        $domain = (string) $request->input('domain', '');
        $key = (string) $request->input('license_key', '');
        $ts = (int) $request->input('ts', 0);
        $sig = (string) $request->input('signature', '');
        if (abs(time() - $ts) > 600) {
            return false;
        }
        $payload = $domain.'|'.$key.'|'.$ts;
        $expect = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expect, $sig);
    }
}
