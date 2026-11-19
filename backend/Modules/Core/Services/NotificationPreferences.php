<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person notification switches, stored in user_notification_preferences:
 *  - event_key "*"              master switch per channel
 *  - event_key "category:<key>" category on/off plus per-channel switches
 *  - any other event_key        exact event overrides (Communication Hub)
 *
 * Every sender asks allows() before delivering. Security notices (OTP, password,
 * sign-in alerts) cannot be switched off so a person can never lock themselves out.
 */
class NotificationPreferences
{
    public const CHANNELS = ['in_app', 'sms', 'email', 'push', 'chat'];

    public const CATEGORIES = ['system', 'security', 'hrm', 'crm', 'projects', 'finance', 'support', 'marketing'];

    public const LOCKED_CATEGORIES = ['security'];

    private const TABLE = 'user_notification_preferences';

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $memo = [];

    private ?bool $ready = null;

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->ready = Schema::hasTable(self::TABLE) && Schema::hasColumn(self::TABLE, 'enabled');
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    public static function categoryFor(string $eventKey): string
    {
        $k = strtolower(trim($eventKey));
        if ($k === '') {
            return 'system';
        }
        $rules = [
            'security' => ['security', 'auth', 'otp', 'password', '2fa', 'login', 'account_locked'],
            'hrm' => ['hrm', 'leave', 'payslip', 'payroll', 'salary', 'shift', 'attendance', 'staff', 'employee', 'offboarding', 'onboarding'],
            'crm' => ['crm', 'lead', 'deal', 'customer', 'account', 'consultation', 'contact'],
            'projects' => ['project', 'task', 'pm', 'milestone', 'appointment', 'reminder', 'workflow'],
            'finance' => ['invoice', 'payment', 'billing', 'license', 'renewal', 'sales', 'accounting', 'finance', 'order', 'wallet'],
            'support' => ['ticket', 'support', 'chat', 'message'],
            'marketing' => ['marketing', 'campaign', 'newsletter', 'promo', 'sequence'],
        ];
        foreach ($rules as $category => $needles) {
            foreach ($needles as $needle) {
                if ($k === $needle || str_starts_with($k, $needle.'.') || str_starts_with($k, $needle.'_') || str_starts_with($k, $needle.':')) {
                    return $category;
                }
            }
        }

        return 'system';
    }

    public static function normalizeChannel(string $channel): string
    {
        return match ($channel) {
            'bale', 'telegram', 'chat' => 'chat',
            'mail', 'email' => 'email',
            'database', 'in_app', 'inapp' => 'in_app',
            'webpush', 'push' => 'push',
            default => $channel,
        };
    }

    public function allows(?int $userId, string $channel, string $eventKey = 'general'): bool
    {
        if (! $userId || ! $this->ready()) {
            return true;
        }
        $channel = self::normalizeChannel($channel);
        $category = self::categoryFor($eventKey);
        if (in_array($category, self::LOCKED_CATEGORIES, true)) {
            return true;
        }
        $rows = $this->rows($userId);
        $col = 'channel_'.$channel;

        $global = $rows['*'] ?? null;
        if ($global && (! $global['enabled'] || (array_key_exists($col, $global) && ! $global[$col]))) {
            return false;
        }
        $cat = $rows['category:'.$category] ?? null;
        if ($cat && (! $cat['enabled'] || (array_key_exists($col, $cat) && ! $cat[$col]))) {
            return false;
        }
        $exact = $rows[$eventKey] ?? null;
        if ($exact && (! ($exact['enabled'] ?? true) || (array_key_exists($col, $exact) && ! $exact[$col]))) {
            return false;
        }

        return true;
    }

    /** Resolve a raw address (mobile or email) to a user and check their switches. */
    public function allowsAddress(string $channel, ?string $address, string $eventKey = 'general'): bool
    {
        $userId = $this->userIdForAddress($channel, $address);

        return $userId === null || $this->allows($userId, $channel, $eventKey);
    }

    public function userIdForAddress(string $channel, ?string $address): ?int
    {
        $address = trim((string) $address);
        if ($address === '' || ! $this->ready()) {
            return null;
        }
        try {
            if (self::normalizeChannel($channel) === 'email') {
                $id = User::query()->whereRaw('lower(email) = ?', [strtolower($address)])->value('id');

                return $id ? (int) $id : null;
            }
            $digits = preg_replace('/\D+/', '', $address) ?? '';
            if (strlen($digits) < 10) {
                return null;
            }
            $tail = substr($digits, -10);
            $id = User::query()->where('phone', 'like', '%'.$tail)->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Full matrix for the UI.
     *
     * @return array{channels: array<string, bool>, categories: array<string, array{enabled: bool, locked: bool, channels: array<string, bool>}>, quiet_hours: array{start: ?string, end: ?string}}
     */
    public function matrix(int $userId): array
    {
        $rows = $this->ready() ? $this->rows($userId) : [];
        $global = $rows['*'] ?? [];
        $channels = [];
        foreach (self::CHANNELS as $c) {
            $channels[$c] = ($global['enabled'] ?? true) ? (bool) ($global['channel_'.$c] ?? true) : false;
        }
        $categories = [];
        foreach (self::CATEGORIES as $cat) {
            $row = $rows['category:'.$cat] ?? [];
            $locked = in_array($cat, self::LOCKED_CATEGORIES, true);
            $perChannel = [];
            foreach (self::CHANNELS as $c) {
                $perChannel[$c] = $locked ? true : (bool) ($row['channel_'.$c] ?? true);
            }
            $categories[$cat] = [
                'enabled' => $locked ? true : (bool) ($row['enabled'] ?? true),
                'locked' => $locked,
                'channels' => $perChannel,
            ];
        }

        return [
            'channels' => $channels,
            'categories' => $categories,
            'quiet_hours' => [
                'start' => isset($global['quiet_hours_start']) ? substr((string) $global['quiet_hours_start'], 0, 5) : null,
                'end' => isset($global['quiet_hours_end']) ? substr((string) $global['quiet_hours_end'], 0, 5) : null,
            ],
        ];
    }

    /**
     * @param  array{channels?: array<string, mixed>, categories?: array<string, mixed>, quiet_hours?: array<string, mixed>}  $input
     */
    public function save(int $userId, array $input): array
    {
        if (! $this->ready()) {
            return $this->matrix($userId);
        }
        DB::transaction(function () use ($userId, $input) {
            if ((isset($input['channels']) && is_array($input['channels'])) || isset($input['quiet_hours'])) {
                $payload = ['enabled' => true];
                foreach (self::CHANNELS as $c) {
                    if (isset($input['channels'][$c])) {
                        $payload['channel_'.$c] = (bool) $input['channels'][$c];
                    }
                }
                if (isset($input['quiet_hours']) && is_array($input['quiet_hours'])) {
                    $payload['quiet_hours_start'] = $this->time($input['quiet_hours']['start'] ?? null);
                    $payload['quiet_hours_end'] = $this->time($input['quiet_hours']['end'] ?? null);
                }
                $this->upsert($userId, '*', $payload);
            }
            foreach ((array) ($input['categories'] ?? []) as $cat => $conf) {
                if (! in_array($cat, self::CATEGORIES, true) || in_array($cat, self::LOCKED_CATEGORIES, true) || ! is_array($conf)) {
                    continue;
                }
                $payload = [];
                if (array_key_exists('enabled', $conf)) {
                    $payload['enabled'] = (bool) $conf['enabled'];
                }
                foreach (self::CHANNELS as $c) {
                    if (isset($conf['channels'][$c])) {
                        $payload['channel_'.$c] = (bool) $conf['channels'][$c];
                    }
                }
                if ($payload !== []) {
                    $this->upsert($userId, 'category:'.$cat, $payload);
                }
            }
        });
        unset($this->memo[$userId]);

        return $this->matrix($userId);
    }

    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memo = [];
        } else {
            unset($this->memo[$userId]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function upsert(int $userId, string $eventKey, array $payload): void
    {
        $existing = DB::table(self::TABLE)->where('user_id', $userId)->where('event_key', $eventKey)->first();
        $payload['updated_at'] = now();
        if ($existing) {
            DB::table(self::TABLE)->where('id', $existing->id)->update($payload);

            return;
        }
        $defaults = ['enabled' => true, 'channel_chat' => true];
        foreach (self::CHANNELS as $c) {
            $defaults['channel_'.$c] = true;
        }
        DB::table(self::TABLE)->insert(array_merge($defaults, $payload, [
            'user_id' => $userId,
            'event_key' => $eventKey,
            'created_at' => now(),
        ]));
    }

    private function time(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
            return null;
        }

        return $v.':00';
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(int $userId): array
    {
        if (! isset($this->memo[$userId])) {
            $out = [];
            try {
                foreach (DB::table(self::TABLE)->where('user_id', $userId)->get() as $row) {
                    $out[(string) $row->event_key] = array_map(
                        static fn ($v) => is_numeric($v) && in_array((string) $v, ['0', '1'], true) ? (bool) (int) $v : $v,
                        (array) $row
                    );
                }
            } catch (\Throwable) {
                $out = [];
            }
            $this->memo[$userId] = $out;
        }

        return $this->memo[$userId];
    }
}
