<?php

namespace Modules\Hrm\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmNotice;
use Modules\Hrm\Services\IranianPayrollCalculator;

/**
 * HR notifications. Always writes a portal notice (hrm_notices), then fans out to the
 * channels enabled in HR settings: in-app (Core notifications), SMS (Integrations / ModirPayamak),
 * Bale and Telegram bots (direct message to the employee's chat id).
 */
class HrmNotifier
{
    public const CHANNELS = ['in_app', 'sms', 'bale', 'telegram'];

    public const EVENTS = ['leave_decision', 'shift_assigned', 'payslip_issued', 'payroll_paid', 'request_decision', 'missing_docs', 'offboarding'];

    /** @var array<string, string> notification kind → event toggle */
    public const KIND_EVENTS = [
        'leave_approved' => 'leave_decision',
        'leave_rejected' => 'leave_decision',
        'shift_assigned' => 'shift_assigned',
        'payslip_issued' => 'payslip_issued',
        'salary_paid' => 'payslip_issued',
        'payroll_paid' => 'payroll_paid',
        'request_approved' => 'request_decision',
        'request_rejected' => 'request_decision',
        'missing_docs' => 'missing_docs',
        'offboarding_started' => 'offboarding',
    ];

    /**
     * @return array<string, string>
     */
    public static function notify(?int $employeeId, string $kind, string $title, string $body): array
    {
        if (Schema::hasTable('hrm_notices')) {
            try {
                self::storeNotice($employeeId, $kind, $title, $body);
            } catch (\Throwable $e) {
                Log::info('HrmNotifier notice skipped: '.$e->getMessage());
            }
        }

        return self::fanoutExternal($employeeId, $kind, $title, $body);
    }

    /**
     * Current notification settings with defaults.
     *
     * @return array{enabled: bool, channels: array<string, bool>, events: array<string, bool>, telegram_bot_token: string, bale_bot_token: string}
     */
    public static function settings(): array
    {
        $s = [];
        try {
            $s = app(IranianPayrollCalculator::class)->settings();
        } catch (\Throwable) {
            // settings table may be missing during early migrate
        }
        $channels = is_array($s['notify_channels'] ?? null) ? $s['notify_channels'] : [];
        $events = is_array($s['notify_events'] ?? null) ? $s['notify_events'] : [];
        $outChannels = [];
        foreach (self::CHANNELS as $c) {
            $outChannels[$c] = (bool) ($channels[$c] ?? ($c === 'in_app'));
        }
        $outEvents = [];
        foreach (self::EVENTS as $e) {
            $outEvents[$e] = (bool) ($events[$e] ?? true);
        }

        return [
            'enabled' => (bool) ($s['external_notifications_enabled'] ?? true),
            'channels' => $outChannels,
            'events' => $outEvents,
            'telegram_bot_token' => (string) ($s['telegram_bot_token'] ?? ''),
            'bale_bot_token' => (string) ($s['bale_bot_token'] ?? ''),
        ];
    }

    public static function telegramToken(): string
    {
        $own = self::settings()['telegram_bot_token'];

        return $own !== '' ? $own : (string) config('integrations.telegram.token', '');
    }

    public static function baleToken(): string
    {
        $own = self::settings()['bale_bot_token'];
        if ($own !== '') {
            return $own;
        }
        if (class_exists(\Modules\Integrations\Services\Bale\BaleSettingsStore::class) && Schema::hasTable('integration_settings')) {
            try {
                $token = app(\Modules\Integrations\Services\Bale\BaleSettingsStore::class)->botToken();
                if ($token !== '') {
                    return $token;
                }
            } catch (\Throwable) {
                // integrations not configured
            }
        }

        return (string) config('integrations.bale.token', '');
    }

    /**
     * @return array<string, string>
     */
    public static function fanoutExternal(?int $employeeId, string $kind, string $title, string $body, bool $force = false): array
    {
        $settings = self::settings();
        $results = [];
        if (! $force) {
            if (! $settings['enabled']) {
                return ['all' => 'disabled'];
            }
            $event = self::KIND_EVENTS[$kind] ?? null;
            if (! $event || ! ($settings['events'][$event] ?? false)) {
                return ['all' => 'event_disabled'];
            }
        }

        $employee = $employeeId && Schema::hasTable('hrm_employees') ? HrmEmployee::query()->find($employeeId) : null;
        $profile = $employee && Schema::hasTable('hrm_employee_profiles')
            ? HrmEmployeeProfile::query()->where('employee_id', $employee->id)->first()
            : null;
        $custom = is_array($profile?->custom_fields) ? $profile->custom_fields : [];
        $text = trim($title."\n".$body);

        foreach (self::CHANNELS as $channel) {
            if (! $settings['channels'][$channel]) {
                $results[$channel] = 'disabled';
                continue;
            }
            try {
                $results[$channel] = match ($channel) {
                    'in_app' => self::sendInApp($employee?->user_id, $kind, $title, $body),
                    'sms' => self::sendSms($employee?->user_id, $employee?->mobile, $kind, $title, $body),
                    'bale' => self::sendBot('bale', self::baleToken(), $profile?->bale_chat_id ?: ($custom['bale_chat_id'] ?? null), $text),
                    'telegram' => self::sendBot('telegram', self::telegramToken(), $profile?->telegram_chat_id ?: ($custom['telegram_chat_id'] ?? null), $text),
                };
            } catch (\Throwable $e) {
                Log::info('HrmNotifier '.$channel.' failed: '.$e->getMessage());
                $results[$channel] = 'failed';
            }
        }

        return $results;
    }

    private static function storeNotice(?int $employeeId, string $kind, string $title, string $body): void
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'is_active' => true,
            'date_from' => now()->toDateString(),
        ];
        if (Schema::hasColumn('hrm_notices', 'employee_id')) {
            $payload['employee_id'] = $employeeId;
        }
        if (Schema::hasColumn('hrm_notices', 'kind')) {
            $payload['kind'] = $kind;
            $dup = HrmNotice::query()->where('kind', $kind)->where('body', $body);
            if ($employeeId && Schema::hasColumn('hrm_notices', 'employee_id')) {
                $dup->where('employee_id', $employeeId);
            }
            if ($dup->exists()) {
                return;
            }
        }
        HrmNotice::query()->create($payload);
    }

    private static function sendInApp(?int $userId, string $kind, string $title, string $body): string
    {
        if (! $userId) {
            return 'skipped_no_user';
        }
        if (class_exists(\Modules\Integrations\Services\NotificationFanout::class) && Schema::hasTable('int_channel_deliveries')) {
            $res = app(\Modules\Integrations\Services\NotificationFanout::class)->dispatch([
                'title' => $title, 'body' => $body, 'event_key' => 'hrm.'.$kind, 'user_id' => $userId, 'channels' => ['in_app'],
            ]);

            return (string) ($res['results']['in_app']['status'] ?? 'skipped');
        }
        if (class_exists(\Modules\Core\Entities\CoreNotification::class)) {
            \Modules\Core\Entities\CoreNotification::query()->create([
                'user_id' => $userId,
                'type' => 'hrm.'.$kind,
                'data' => ['title' => $title, 'body' => $body, 'source' => 'hrm'],
                'is_read' => false,
            ]);

            return 'delivered';
        }

        return 'skipped_unavailable';
    }

    private static function sendSms(?int $userId, ?string $phone, string $kind, string $title, string $body): string
    {
        if (! $phone) {
            return 'skipped_no_phone';
        }
        if (! class_exists(\Modules\Integrations\Services\NotificationFanout::class) || ! Schema::hasTable('int_channel_deliveries')) {
            return 'skipped_unavailable';
        }
        $priority = in_array($kind, ['salary_paid', 'payroll_paid', 'payslip_issued', 'missing_docs'], true) ? 'high' : 'normal';
        $res = app(\Modules\Integrations\Services\NotificationFanout::class)->dispatch([
            'title' => $title, 'body' => $body, 'priority' => $priority, 'event_key' => 'hrm.'.$kind,
            'user_id' => $userId, 'phone' => $phone, 'channels' => ['sms'],
        ]);
        $status = (string) ($res['results']['sms']['status'] ?? 'skipped');
        $reason = (string) ($res['results']['sms']['reason'] ?? '');

        return $status === 'queued' && $reason === 'provider_not_configured' ? 'skipped_not_configured' : $status;
    }

    private static function sendBot(string $provider, string $token, mixed $chatId, string $text): string
    {
        if ($token === '') {
            return 'skipped_no_token';
        }
        if ($chatId === null || trim((string) $chatId) === '') {
            return 'skipped_no_chat_id';
        }
        $base = $provider === 'bale' ? 'https://tapi.bale.ai/bot' : 'https://api.telegram.org/bot';
        $response = Http::asJson()->acceptJson()->timeout(10)->post(
            $base.$token.'/sendMessage',
            ['chat_id' => trim((string) $chatId), 'text' => $text]
        );

        return $response->successful() && (bool) ($response->json('ok') ?? true) ? 'sent' : 'failed';
    }

    public static function missingDocs(int $employeeId, string $docTitle): void
    {
        self::notify(
            $employeeId,
            'missing_docs',
            'مدارک ناقص',
            'مدرک «'.$docTitle.'» ناقص یا منقضی است. لطفاً در پورتال کارکنان بارگذاری کنید.'
        );
    }
}
