<?php

namespace Modules\Integrations\Services\Payments;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayHttp
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array{status:int,json:array<string,mixed>,ok:bool}
     */
    public function postJson(string $url, array $body, array $headers = [], ?string $token = null, ?array $basic = null): array
    {
        try {
            $pending = Http::timeout(25)->acceptJson()->asJson()->withHeaders($headers);
            if ($token) {
                $pending = $pending->withToken($token);
            }
            if ($basic) {
                $pending = $pending->withBasicAuth($basic[0], $basic[1]);
            }
            $response = $pending->post($url, $body);
        } catch (\Throwable $e) {
            Log::warning('payment.gateway.http_error', [
                'url' => $this->safeUrl($url),
                'message' => $e->getMessage(),
            ]);

            return ['status' => 0, 'json' => [], 'ok' => false];
        }

        return $this->pack($url, $response);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array{status:int,json:array<string,mixed>,ok:bool}
     */
    public function postForm(string $url, array $form, ?array $basic = null): array
    {
        try {
            $pending = Http::timeout(25)->acceptJson()->asForm();
            if ($basic) {
                $pending = $pending->withBasicAuth($basic[0], $basic[1]);
            }
            $response = $pending->post($url, $form);
        } catch (\Throwable $e) {
            Log::warning('payment.gateway.http_error', [
                'url' => $this->safeUrl($url),
                'message' => $e->getMessage(),
            ]);

            return ['status' => 0, 'json' => [], 'ok' => false];
        }

        return $this->pack($url, $response);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, string>  $headers
     * @return array{status:int,json:array<string,mixed>,ok:bool}
     */
    public function getJson(string $url, array $query = [], array $headers = [], ?string $token = null): array
    {
        try {
            $pending = Http::timeout(25)->acceptJson()->withHeaders($headers);
            if ($token) {
                $pending = $pending->withToken($token);
            }
            $response = $pending->get($url, $query);
        } catch (\Throwable $e) {
            Log::warning('payment.gateway.http_error', [
                'url' => $this->safeUrl($url),
                'message' => $e->getMessage(),
            ]);

            return ['status' => 0, 'json' => [], 'ok' => false];
        }

        return $this->pack($url, $response);
    }

    /**
     * @return array{status:int,json:array<string,mixed>,ok:bool}
     */
    private function pack(string $url, Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }
        if (! $response->successful()) {
            Log::warning('payment.gateway.http_status', [
                'url' => $this->safeUrl($url),
                'status' => $response->status(),
                'body' => $this->redact($response->body()),
            ]);
        } else {
            Log::info('payment.gateway.http_ok', [
                'url' => $this->safeUrl($url),
                'status' => $response->status(),
            ]);
        }

        return [
            'status' => $response->status(),
            'json' => $json,
            'ok' => $response->successful(),
        ];
    }

    private function safeUrl(string $url): string
    {
        return (string) preg_replace('/([?&](?:token|password|secret|client_secret)=)[^&]*/i', '$1***', $url);
    }

    private function redact(string $body): string
    {
        $body = (string) preg_replace('/("(?:access_token|refresh_token|password|client_secret|merchant_id|paymentToken)"\s*:\s*")[^"]*/i', '$1***', $body);

        return mb_substr($body, 0, 500);
    }
}
