<?php

namespace Modules\Crm\Services;

use App\Support\ModuleMonitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Crm\Entities\CrmEInvoice;

class MoadianClient
{
    public function sandbox(): bool
    {
        if ((bool) config('integrations.moadian.sandbox') || (bool) config('integrations.sandbox')) {
            return true;
        }

        return app()->bound('request') && request()->header('X-Webino-Sandbox') === '1';
    }

    public function baseUrl(): string
    {
        if ($this->sandbox()) {
            $sandbox = trim((string) config('integrations.moadian.sandbox_base_url'));
            if ($sandbox !== '') {
                return rtrim($sandbox, '/');
            }
        }

        return rtrim(trim((string) config('integrations.moadian.base_url')), '/');
    }

    /**
     * Structured سامانه مودیان packet derived from the local invoice document.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function packet(array $document): array
    {
        $items = [];
        $prdis = 0.0;
        $dis = 0.0;
        $adis = 0.0;
        $vam = 0.0;
        $bill = 0.0;
        foreach ((array) ($document['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (float) ($item['qty'] ?? 0);
            $fee = (float) ($item['fee'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $before = round($qty * $fee, 2);
            $after = round(max(0, $before - $discount), 2);
            $vat = (float) ($item['vat_amount'] ?? 0);
            $line = round($after + $vat, 2);
            $prdis += $before;
            $dis += $discount;
            $adis += $after;
            $vam += $vat;
            $bill += $line;
            $items[] = [
                'sstid' => (string) ($item['commodity_code'] ?? ''),
                'sstt' => (string) ($item['description'] ?? ''),
                'mu' => (string) ($item['unit'] ?? '1627'),
                'am' => $qty,
                'fee' => $fee,
                'prdis' => $before,
                'dis' => $discount,
                'adis' => $after,
                'vra' => (float) ($item['vat_rate'] ?? 0),
                'vam' => $vat,
                'tsstam' => $line,
            ];
        }
        $issued = strtotime((string) ($document['issue_date'] ?? 'now').' 12:00:00') ?: time();
        $buyerType = ($document['buyer']['type'] ?? 'legal') === 'natural' ? 2 : 1;

        return [
            'header' => [
                'taxid' => (string) ($document['taxid'] ?? ''),
                'indatim' => $issued * 1000,
                'inty' => (int) ($document['invoice_type'] ?? 1),
                'inno' => (string) ($document['serial'] ?? ''),
                'inp' => (int) ($document['pattern'] ?? 1),
                'ins' => 1,
                'tins' => (string) ($document['seller']['economic_code'] ?? config('integrations.moadian.economic_code') ?? ''),
                'tinb' => (string) ($document['buyer']['economic_code'] ?? ''),
                'tob' => $buyerType,
                'bid' => (string) ($document['buyer']['national_id'] ?? ''),
                'tprdis' => round($prdis, 2),
                'tdis' => round($dis, 2),
                'tadis' => round($adis, 2),
                'tvam' => round($vam, 2),
                'todam' => 0,
                'tbill' => round($bill, 2),
                'setm' => 1,
                'fiscal_id' => (string) config('integrations.moadian.fiscal_id', ''),
            ],
            'body' => $items,
            'payments' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $packet
     */
    public function sign(array $packet): ?string
    {
        $pem = $this->privateKey();
        if ($pem === null) {
            return null;
        }
        $canonical = json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($canonical)) {
            return null;
        }
        $ok = openssl_sign($canonical, $raw, $pem, OPENSSL_ALGO_SHA256);

        return $ok ? base64_encode($raw) : null;
    }

    public function submit(CrmEInvoice $invoice, bool $queueRetry = true): CrmEInvoice
    {
        $lock = Cache::lock('moadian:'.$invoice->id, 30);
        if (! $lock->get()) {
            return $invoice->fresh();
        }
        try {
            $invoice->refresh();
            if (in_array($invoice->status, ['accepted', 'rejected'], true)) {
                return $invoice;
            }
            $packet = (array) data_get($invoice->document, 'moadian', []);
            if ($packet === []) {
                $packet = $this->packet((array) $invoice->document);
            }
            $base = $this->baseUrl();
            $hook = trim((string) config('integrations.moadian.hook_url'));
            if ($base === '' && $hook === '') {
                $invoice->update([
                    'status' => 'queued',
                    'sandbox' => $this->sandbox(),
                    'provider_response' => ['mode' => 'queued_local'],
                ]);
                ModuleMonitor::hook('crm', 'moadian_queued_local', ['invoice_id' => $invoice->id]);

                return $invoice->fresh();
            }
            try {
                $response = $base !== ''
                    ? $this->postInvoice($base, $packet)
                    : Http::timeout(20)->acceptJson()->post($hook, $packet);
            } catch (\Throwable $e) {
                return $this->retry($invoice, $e->getMessage(), $queueRetry);
            }
            if ($response->serverError() || $response->status() === 429) {
                return $this->retry($invoice, 'http_'.$response->status(), $queueRetry);
            }
            $body = $response->json();
            $reference = is_array($body) ? (string) ($body['referenceNumber'] ?? $body['uid'] ?? '') : '';
            $invoice->update([
                'status' => $response->successful() ? 'accepted' : 'rejected',
                'sandbox' => $this->sandbox(),
                'reference_number' => $reference !== '' ? $reference : null,
                'next_retry_at' => null,
                'provider_response' => [
                    'code' => $response->status(),
                    'body' => $body ?? $response->body(),
                    'signed' => $this->sign($packet) !== null,
                ],
            ]);
            ModuleMonitor::hook('crm', 'moadian_submitted', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status,
            ]);

            return $invoice->fresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $packet
     */
    private function postInvoice(string $base, array $packet): \Illuminate\Http\Client\Response
    {
        $request = Http::timeout(20)->acceptJson();
        $token = $this->accessToken($base);
        if ($token) {
            $request = $request->withToken($token);
        }
        $signature = $this->sign($packet);
        $body = ['payload' => $packet];
        if ($signature) {
            $body['signature'] = $signature;
        }

        return $request->post($base.'/invoices', $body);
    }

    private function accessToken(string $base): ?string
    {
        $clientId = (string) config('integrations.moadian.client_id');
        $secret = (string) config('integrations.moadian.client_secret');
        if ($clientId === '' || $secret === '') {
            return null;
        }

        return Cache::remember('moadian.token.'.md5($base.$clientId), 3000, function () use ($base, $clientId, $secret) {
            $response = Http::asForm()->timeout(20)->post($base.'/auth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $secret,
            ]);
            if (! $response->successful()) {
                throw new \RuntimeException('moadian_auth_failed');
            }

            return (string) $response->json('access_token');
        });
    }

    private function retry(CrmEInvoice $invoice, string $reason, bool $queueRetry): CrmEInvoice
    {
        $attempts = (int) $invoice->attempts + 1;
        $max = max(1, (int) config('integrations.moadian.retry_times', 5));
        if ($attempts >= $max) {
            $invoice->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'next_retry_at' => null,
                'provider_response' => ['mode' => 'failed', 'reason' => $reason],
            ]);
            ModuleMonitor::hook('crm', 'moadian_failed', ['invoice_id' => $invoice->id, 'reason' => $reason]);

            return $invoice->fresh();
        }
        $invoice->update([
            'status' => 'retry',
            'attempts' => $attempts,
            'sandbox' => $this->sandbox(),
            'next_retry_at' => now()->addMinutes(2 ** min($attempts, 6)),
            'provider_response' => ['mode' => 'retry', 'reason' => $reason],
        ]);
        ModuleMonitor::hook('crm', 'moadian_retry', ['invoice_id' => $invoice->id, 'attempts' => $attempts]);
        if ($queueRetry && config('queue.default') !== 'sync') {
            \Modules\Crm\Jobs\RetryMoadianSubmitJob::dispatch($invoice->id)->delay(now()->addMinutes(2 ** min($attempts, 6)));
        }

        return $invoice->fresh();
    }

    private function privateKey(): ?string
    {
        $inline = (string) config('integrations.moadian.private_key');
        if ($inline !== '') {
            return str_replace('\\n', "\n", $inline);
        }
        $path = (string) config('integrations.moadian.private_key_path');
        if ($path !== '' && is_file($path)) {
            $contents = file_get_contents($path);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        return null;
    }
}
