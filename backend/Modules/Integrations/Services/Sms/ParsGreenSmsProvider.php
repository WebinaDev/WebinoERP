<?php

namespace Modules\Integrations\Services\Sms;

use Illuminate\Support\Facades\Http;
use Modules\Integrations\Contracts\SmsProviderInterface;

class ParsGreenSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message, array $settings = []): bool
    {
        $apiKey = (string) ($settings['api_key'] ?? '');
        if ($apiKey === '') {
            return false;
        }
        $url = (string) ($settings['url'] ?? 'https://api.sms.ir/v1/send/likeToLike');
        $res = Http::withHeaders([
            'X-API-KEY' => $apiKey,
        ])->asJson()->post($url, [
            'mobile' => $to,
            'message' => $message,
        ]);

        return $res->successful();
    }
}
