<?php

namespace Modules\Integrations\Console;

use Illuminate\Console\Command;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\EmailAccount;
use Modules\Integrations\Services\OAuthTokenRefresher;

class RefreshOAuthCommand extends Command
{
    protected $signature = 'webino:oauth:refresh';

    protected $description = 'Refresh Google and Microsoft tokens before they expire';

    public function handle(OAuthTokenRefresher $refresher): int
    {
        $horizon = now()->addMinutes(10);
        $count = 0;
        $calendars = CalendarAccount::query()
            ->whereNotNull('refresh_token')
            ->where(function ($query) use ($horizon) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', $horizon);
            })
            ->get();
        foreach ($calendars as $account) {
            try {
                $refresher->ensureFresh($account, 600);
            } catch (\Throwable) {
                // needs_reauth is persisted; keep scanning the rest
            }
            $count++;
        }
        $mailboxes = EmailAccount::query()
            ->whereIn('provider', ['google', 'microsoft'])
            ->whereNotNull('refresh_token')
            ->where(function ($query) use ($horizon) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', $horizon);
            })
            ->get();
        foreach ($mailboxes as $account) {
            try {
                $refresher->ensureFresh($account, 600);
            } catch (\Throwable) {
                // needs_reauth is persisted; keep scanning the rest
            }
            $count++;
        }
        $this->info('oauth refresh checked '.$count);

        return self::SUCCESS;
    }
}
