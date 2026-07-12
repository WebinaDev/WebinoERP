<?php

namespace Modules\Integrations\Services\Bale;

use Illuminate\Support\Facades\Http;

/**
 * Bale Bot HTTP API (Telegram-compatible). Base: https://tapi.bale.ai/bot{token}/{method}
 */
class BaleApiClient
{
    public function __construct(private string $token) {}

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    private function url(string $method): string
    {
        return 'https://tapi.bale.ai/bot'.rawurlencode($this->token).'/'.$method;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    public function post(string $method, array $body = []): ?array
    {
        if (! $this->hasToken()) {
            return null;
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post($this->url($method), $body);

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setWebhook(string $url, ?string $secretToken = null): ?array
    {
        $body = ['url' => $url];
        if ($secretToken !== null && $secretToken !== '') {
            $body['secret_token'] = $secretToken;
        }

        return $this->post('setWebhook', $body);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWebhookInfo(): ?array
    {
        return $this->post('getWebhookInfo', []);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function sendMessage(array $params): ?array
    {
        return $this->post('sendMessage', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function answerCallbackQuery(array $params): ?array
    {
        return $this->post('answerCallbackQuery', $params);
    }
}
