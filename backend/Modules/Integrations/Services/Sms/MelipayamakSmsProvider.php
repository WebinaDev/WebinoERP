<?php

namespace Modules\Integrations\Services\Sms;

use Illuminate\Support\Facades\Http;
use Modules\Integrations\Contracts\SmsProviderInterface;

class MelipayamakSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message, array $settings = []): bool
    {
        $username = (string) ($settings['username'] ?? '');
        $password = (string) ($settings['password'] ?? '');
        if ($username === '' || $password === '') {
            return false;
        }
        $endpoint = $settings['endpoint'] ?? 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
        $res = Http::asJson()->post($endpoint, [
            'username' => $username,
            'password' => $password,
            'to' => $to,
            'text' => $message,
        ]);

        return $res->successful();
    }
}
