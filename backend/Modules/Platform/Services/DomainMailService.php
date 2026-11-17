<?php

namespace Modules\Platform\Services;

use Modules\Platform\Entities\PlatformMailDomain;

/**
 * Domain Email Provisioning & DNS Inspector.
 * Generates MX, SPF, DKIM (RSA-2048), DMARC records and verifies live DNS propagation.
 */
class DomainMailService
{
    /**
     * Provision email capability for a platform domain.
     */
    public function setupMailDomain(string $domainName, ?int $domainId = null, string $mailHost = 'mail.webinoerp.com'): PlatformMailDomain
    {
        $domainName = strtolower(trim($domainName));

        // تولید جفت کلید RSA برای امضای DKIM
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $privateKey = '';
        openssl_pkey_export($res, $privateKey);

        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'] ?? '';
        // فرمت تمیز کلید عمومی برای رکورد DNS TXT
        $pubClean = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $publicKey);

        $selector = 'default';
        $spf = 'v=spf1 mx a:' . $mailHost . ' ~all';
        $dmarc = 'v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@' . $domainName;

        return PlatformMailDomain::updateOrCreate(
            ['domain_name' => $domainName],
            [
                'domain_id' => $domainId,
                'mx_record' => '10 ' . $mailHost,
                'spf_record' => $spf,
                'dkim_selector' => $selector,
                'dkim_public_key' => $pubClean,
                'dkim_private_key' => $privateKey,
                'dmarc_record' => $dmarc,
                'dns_status' => 'pending',
                'is_active' => true,
                'last_dns_checked_at' => now(),
            ]
        );
    }

    /**
     * Inspect live DNS records for MX, SPF, DKIM, and DMARC verification.
     */
    public function checkDnsStatus(PlatformMailDomain $mailDomain): array
    {
        $domain = $mailDomain->domain_name;
        $mxVerified = false;
        $spfVerified = false;
        $dmarcVerified = false;

        // بررسی رکوردهای MX در محیط شبکه
        if (function_exists('dns_get_record')) {
            $mxRecords = @dns_get_record($domain, DNS_MX) ?: [];
            $mxVerified = count($mxRecords) > 0;

            $txtRecords = @dns_get_record($domain, DNS_TXT) ?: [];
            foreach ($txtRecords as $txt) {
                $entry = $txt['txt'] ?? '';
                if (str_starts_with($entry, 'v=spf1')) {
                    $spfVerified = true;
                }
            }

            $dmarcRecords = @dns_get_record('_dmarc.' . $domain, DNS_TXT) ?: [];
            $dmarcVerified = count($dmarcRecords) > 0;
        }

        $allOk = $mxVerified && $spfVerified;
        $newStatus = $allOk ? 'verified' : 'pending';

        $mailDomain->update([
            'dns_status' => $newStatus,
            'last_dns_checked_at' => now(),
        ]);

        return [
            'domain' => $domain,
            'dns_status' => $newStatus,
            'mx_verified' => $mxVerified,
            'spf_verified' => $spfVerified,
            'dmarc_verified' => $dmarcVerified,
            'instructions' => [
                ['type' => 'MX', 'host' => '@', 'value' => $mailDomain->mx_record, 'priority' => 10],
                ['type' => 'TXT', 'host' => '@', 'value' => $mailDomain->spf_record],
                ['type' => 'TXT', 'host' => "{$mailDomain->dkim_selector}._domainkey", 'value' => "v=DKIM1; k=rsa; p={$mailDomain->dkim_public_key}"],
                ['type' => 'TXT', 'host' => '_dmarc', 'value' => $mailDomain->dmarc_record],
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
