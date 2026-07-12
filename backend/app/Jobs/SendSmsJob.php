<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Contracts\SmsProviderInterface;
use Modules\Integrations\Services\Sms\MelipayamakSmsProvider;
use Modules\Integrations\Services\Sms\ParsGreenSmsProvider;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $provider,
        public string $to,
        public string $message,
        public array $settings = []
    ) {
        $this->onQueue('sms');
    }

    public function handle(): void
    {
        $driver = match ($this->provider) {
            'melipayamak' => new MelipayamakSmsProvider,
            'parsgreen' => new ParsGreenSmsProvider,
            default => null,
        };
        if (! $driver instanceof SmsProviderInterface) {
            Log::channel('single')->info('sms.job.skip', ['provider' => $this->provider]);

            return;
        }
        $ok = $driver->send($this->to, $this->message, $this->settings);
        if (! $ok) {
            throw new \RuntimeException('SMS provider returned failure');
        }
    }
}
