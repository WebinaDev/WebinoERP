<?php

namespace Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Entities\PlatformNotificationChannel;
use Throwable;

class PlatformNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $event, string $message, array $context = []): void
    {
        $channels = PlatformNotificationChannel::query()->where('enabled', true)->get();
        foreach ($channels as $channel) {
            try {
                $this->send($channel, $event, $message, $context);
            } catch (Throwable) {
                // best-effort
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function send(PlatformNotificationChannel $channel, string $event, string $message, array $context): void
    {
        $cfg = $channel->config ?? [];
        $text = "[Webino Platform] {$event}: {$message}";
        if ($context !== []) {
            $text .= "\n".json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        match ($channel->type) {
            'discord', 'slack' => $this->webhook($cfg['webhook_url'] ?? null, $text),
            'telegram' => $this->telegram($cfg, $text),
            'email' => $this->email($cfg['to'] ?? null, $event, $text),
            default => null,
        };
    }

    protected function webhook(?string $url, string $text): void
    {
        if (! $url) {
            return;
        }
        Http::timeout(8)->post($url, ['content' => $text, 'text' => $text]);
    }

    /** @param  array<string, mixed>  $cfg */
    protected function telegram(array $cfg, string $text): void
    {
        $token = $cfg['bot_token'] ?? '';
        $chat = $cfg['chat_id'] ?? '';
        if ($token === '' || $chat === '') {
            return;
        }
        Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chat,
            'text' => $text,
        ]);
    }

    protected function email(?string $to, string $subject, string $text): void
    {
        if (! $to) {
            return;
        }
        Mail::raw($text, function ($mail) use ($to, $subject) {
            $mail->to($to)->subject('[Webino] '.$subject);
        });
    }
}
