<?php

namespace Modules\Platform\Services;

use Modules\Platform\Entities\PlatformDnsRecord;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformUptimeCheck;

/**
 * Enterprise DNS, SSL Lifecycle & Uptime Monitoring Service.
 */
class DomainOperationsService
{
    /**
     * Add or update a DNS record in the domain zone.
     */
    public function upsertDnsRecord(PlatformDomain $domain, array $data): PlatformDnsRecord
    {
        return PlatformDnsRecord::updateOrCreate(
            [
                'domain_id' => $domain->id,
                'type' => strtoupper($data['type']),
                'name' => trim($data['name']),
            ],
            [
                'value' => trim($data['value']),
                'ttl' => (int) ($data['ttl'] ?? 3600),
                'priority' => isset($data['priority']) ? (int) $data['priority'] : null,
                'is_proxied' => (bool) ($data['is_proxied'] ?? false),
                'status' => 'active',
            ]
        );
    }

    /**
     * Perform HTTP/HTTPS health and latency check for the domain.
     */
    public function checkDomainUptime(PlatformDomain $domain): PlatformUptimeCheck
    {
        $protocol = $domain->force_https ? 'https://' : 'http://';
        $targetUrl = $protocol . $domain->domain;

        $startTime = microtime(true);
        $httpStatus = null;
        $isHealthy = false;
        $error = null;

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5.0,
                    'ignore_errors' => true,
                    'user_agent' => 'WebinoERP-UptimeBot/1.0',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $fp = @fopen($targetUrl, 'r', false, $context);
            $endTime = microtime(true);
            $latencyMs = (int) (($endTime - $startTime) * 1000);

            if ($fp) {
                $meta = stream_get_meta_data($fp);
                fclose($fp);
                $statusLine = $meta['wrapper_data'][0] ?? '';
                if (preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $matches)) {
                    $httpStatus = (int) $matches[1];
                    $isHealthy = ($httpStatus >= 200 && $httpStatus < 400);
                }
            } else {
                $latencyMs = (int) (($endTime - $startTime) * 1000);
                $error = 'ارتباط با سرور میزبان برقرار نشد (Connection Timeout).';
            }
        } catch (\Throwable $e) {
            $latencyMs = 5000;
            $error = $e->getMessage();
        }

        $statusStr = $isHealthy ? 'healthy' : ($httpStatus ? 'degraded' : 'down');
        $domain->update([
            'last_uptime_status' => $statusStr,
            'last_uptime_checked_at' => now(),
        ]);

        return PlatformUptimeCheck::create([
            'domain_id' => $domain->id,
            'url' => $targetUrl,
            'http_status' => $httpStatus,
            'response_time_ms' => $latencyMs,
            'is_healthy' => $isHealthy,
            'error_message' => $error,
            'checked_at' => now(),
        ]);
    }

    /**
     * Inspect and refresh SSL Certificate metadata & expiration timestamp.
     */
    public function inspectSslCertificate(PlatformDomain $domain): array
    {
        $host = $domain->domain;
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        if (! $client) {
            return [
                'status' => 'failed',
                'error' => "خطا در اتصال امن SSL: {$errstr}",
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (! $cert) {
            return ['status' => 'failed', 'error' => 'گواهی‌نامه یافت نشد.'];
        }

        $parsed = openssl_x509_parse($cert);
        $validTo = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null;
        $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? 'Let\'s Encrypt');

        $domain->update([
            'ssl_status' => 'active',
            'ssl_expires_at' => $validTo,
            'ssl_issuer' => $issuer,
        ]);

        return [
            'status' => 'active',
            'issuer' => $issuer,
            'expires_at' => $validTo,
            'days_remaining' => $validTo ? (int) ((strtotime($validTo) - time()) / 86400) : 0,
        ];
    }
}
