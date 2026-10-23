<?php

namespace Modules\Core\Console;

use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Events\LicenseExpiringSoon;
use Modules\Core\Services\LicenseRenewal\LicenseRenewalService;
use Throwable;

/**
 * Expiring-soon reminders (default 7/3/1 days) with the signed renewal link.
 * In-app (CoreNotification to the license owner) is ON by default, SMS to the customer is OFF.
 * Each (license, expiry date, day) pair is sent once.
 */
class LicenseRenewalRemindersCommand extends Command
{
    protected $signature = 'core:license-renewal-reminders {--dry-run}';

    protected $description = 'Send expiring-soon license renewal reminders with the renewal link.';

    public function handle(LicenseRenewalService $renewals): int
    {
        $cfg = LicenseRenewalService::settings()['reminders'];
        if (! $cfg['enabled']) {
            $this->line('reminders disabled');

            return self::SUCCESS;
        }
        $sent = 0;
        foreach ($cfg['days'] as $days) {
            $day = now()->addDays($days);
            $licenses = CoreLicense::query()
                ->whereNotIn('status', ['cancelled', 'revoked'])
                ->whereBetween('expires_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->get();
            foreach ($licenses as $license) {
                $meta = is_array($license->meta) ? $license->meta : [];
                $key = Carbon::parse($license->expires_at)->toDateString().':'.$days;
                $done = (array) ($meta['renewal_reminders'] ?? []);
                if (in_array($key, $done, true)) {
                    continue;
                }
                $url = $renewals->renewUrl($license, 'https://'.$license->domain.'/');
                if ($this->option('dry-run')) {
                    $this->line("{$license->id} {$license->domain} {$days}d");

                    continue;
                }
                try {
                    event(new LicenseExpiringSoon($license->id, $days, $url));
                    $title = 'لایسنس '.$license->domain.' '.$days.' روز دیگر منقضی می‌شود';
                    if ($cfg['in_app'] && $license->created_by) {
                        StaffNotifier::notify((int) $license->created_by, 'license.expiring', $title, 'لینک تمدید: '.$url, ['license_id' => $license->id, 'renew_url' => $url, 'days_left' => $days]);
                    }
                    if ($cfg['sms']) {
                        $renewals->sendSms($renewals->accountId($license), $title.'. تمدید: '.$url);
                    }
                } catch (Throwable $e) {
                    $this->line("{$license->id} error ".$e->getMessage());

                    continue;
                }
                $done[] = $key;
                $meta['renewal_reminders'] = array_slice($done, -20);
                $license->meta = $meta;
                $license->saveQuietly();
                $sent++;
            }
        }
        $this->line("sent {$sent}");

        return self::SUCCESS;
    }
}
