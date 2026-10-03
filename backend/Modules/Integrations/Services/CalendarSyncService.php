<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\CalendarLink;
use Modules\Projects\Entities\ProjectTask;

class CalendarSyncService
{
    public function authorizationUrl(string $provider, int $userId): string
    {
        $state = Str::random(40);
        Cache::put($this->stateKey($state), ['user_id' => $userId, 'provider' => $provider], now()->addMinutes(20));

        if ($provider === 'outlook') {
            $tenant = (string) config('integrations.microsoft.tenant', 'common');
            $query = http_build_query([
                'client_id' => (string) config('integrations.microsoft.client_id'),
                'response_type' => 'code',
                'redirect_uri' => $this->redirect('outlook'),
                'response_mode' => 'query',
                'scope' => 'offline_access Calendars.ReadWrite User.Read',
                'state' => $state,
            ]);

            return 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/authorize?'.$query;
        }

        $query = http_build_query([
            'client_id' => (string) config('integrations.google.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirect('google'),
            'scope' => 'https://www.googleapis.com/auth/calendar openid email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function handleCallback(string $provider, string $code, string $state): CalendarAccount
    {
        $cached = Cache::pull($this->stateKey($state));
        if (! is_array($cached) || ($cached['provider'] ?? '') !== $provider) {
            throw new \InvalidArgumentException('Invalid OAuth state.');
        }
        $tokens = $provider === 'outlook' ? $this->exchangeOutlook($code) : $this->exchangeGoogle($code);

        return $this->storeAccount((int) $cached['user_id'], $provider, $tokens);
    }

    /**
     * @param  array{access_token: string, refresh_token?: string|null, expires_in?: int, email?: string|null}  $tokens
     */
    public function storeAccount(int $userId, string $provider, array $tokens, string $calendarId = 'primary'): CalendarAccount
    {
        $account = CalendarAccount::query()->updateOrCreate(
            ['user_id' => $userId, 'provider' => $provider, 'calendar_id' => $calendarId],
            [
                'email' => $tokens['email'] ?? null,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : now()->addHour(),
                'status' => 'active',
            ]
        );
        $this->registerWatch($account);

        return $account->fresh();
    }

    /**
     * @return array{pulled: int, pushed: int, removed: int}
     */
    public function sync(CalendarAccount $account): array
    {
        $this->refreshIfNeeded($account);
        $stats = $account->provider === 'outlook'
            ? $this->pullOutlook($account)
            : $this->pullGoogle($account);
        $stats['pushed'] = $this->push($account);
        $account->update(['last_synced_at' => now(), 'status' => 'active']);

        return $stats;
    }

    public function syncByChannel(string $channelId): bool
    {
        $account = CalendarAccount::query()->where('webhook_channel_id', $channelId)->first();
        if (! $account) {
            return false;
        }
        $this->sync($account);

        return true;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function syncOutlookNotifications(array $body, ?string $expectedState = null): int
    {
        $count = 0;
        foreach ((array) ($body['value'] ?? []) as $note) {
            if (! is_array($note)) {
                continue;
            }
            if ($expectedState && ($note['clientState'] ?? '') !== $expectedState) {
                continue;
            }
            $sub = (string) ($note['subscriptionId'] ?? '');
            if ($sub !== '' && $this->syncByChannel($sub)) {
                $count++;
            }
        }

        return $count;
    }

    private function pullGoogle(CalendarAccount $account): array
    {
        $response = Http::withToken((string) $account->access_token)
            ->acceptJson()
            ->timeout(20)
            ->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode((string) $account->calendar_id).'/events', [
                'singleEvents' => 'true',
                'showDeleted' => 'true',
                'maxResults' => 100,
            ]);
        if (! $response->successful()) {
            $account->update(['status' => 'error']);
            throw new \RuntimeException('Google calendar pull failed.');
        }
        $pulled = 0;
        $removed = 0;
        foreach ((array) $response->json('items', []) as $event) {
            if (! is_array($event) || empty($event['id'])) {
                continue;
            }
            if (($event['status'] ?? '') === 'cancelled') {
                $this->cancelLocal($account, (string) $event['id']);
                $removed++;
                continue;
            }
            $this->applyRemote($account, (string) $event['id'], [
                'title' => (string) ($event['summary'] ?? 'Event'),
                'start' => $this->googleWhen($event['start'] ?? []),
                'end' => $this->googleWhen($event['end'] ?? []),
                'kind' => (string) data_get($event, 'extendedProperties.private.webina_kind', 'appointment'),
                'local_id' => data_get($event, 'extendedProperties.private.webina_id'),
            ], $event);
            $pulled++;
        }

        return ['pulled' => $pulled, 'pushed' => 0, 'removed' => $removed];
    }

    private function pullOutlook(CalendarAccount $account): array
    {
        $response = Http::withToken((string) $account->access_token)
            ->acceptJson()
            ->timeout(20)
            ->get('https://graph.microsoft.com/v1.0/me/events', [
                '$top' => 50,
                '$select' => 'id,subject,start,end,categories,isCancelled',
            ]);
        if (! $response->successful()) {
            $account->update(['status' => 'error']);
            throw new \RuntimeException('Outlook calendar pull failed.');
        }
        $pulled = 0;
        $removed = 0;
        foreach ((array) $response->json('value', []) as $event) {
            if (! is_array($event) || empty($event['id'])) {
                continue;
            }
            if (! empty($event['isCancelled'])) {
                $this->cancelLocal($account, (string) $event['id']);
                $removed++;
                continue;
            }
            $categories = array_map('strval', (array) ($event['categories'] ?? []));
            $kind = 'appointment';
            $localId = null;
            foreach ($categories as $category) {
                if (str_starts_with($category, 'webina:')) {
                    $kind = substr($category, 7);
                }
                if (str_starts_with($category, 'webina-id:')) {
                    $localId = substr($category, 10);
                }
            }
            $this->applyRemote($account, (string) $event['id'], [
                'title' => (string) ($event['subject'] ?? 'Event'),
                'start' => (string) data_get($event, 'start.dateTime', now()->toIso8601String()),
                'end' => (string) data_get($event, 'end.dateTime', now()->addHour()->toIso8601String()),
                'kind' => $kind === 'task' ? 'task' : 'appointment',
                'local_id' => $localId,
            ], $event);
            $pulled++;
        }

        return ['pulled' => $pulled, 'pushed' => 0, 'removed' => $removed];
    }

    /**
     * @param  array{title: string, start: string, end: string, kind: string, local_id: mixed}  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function applyRemote(CalendarAccount $account, string $externalId, array $mapped, array $raw): void
    {
        $link = CalendarLink::query()->where('account_id', $account->id)->where('external_id', $externalId)->first();
        $kind = $mapped['kind'] === 'task' ? 'task' : 'appointment';
        $localId = $link?->local_id ?: (is_numeric($mapped['local_id'] ?? null) ? (int) $mapped['local_id'] : null);

        if ($kind === 'task') {
            $task = $localId ? ProjectTask::query()->find($localId) : null;
            if (! $task) {
                $task = ProjectTask::query()->create([
                    'title' => $mapped['title'],
                    'status' => 'open',
                    'due_at' => $mapped['end'],
                    'start_at' => $mapped['start'],
                    'created_by' => $account->user_id,
                ]);
            } else {
                $task->update([
                    'title' => $mapped['title'],
                    'due_at' => $mapped['end'],
                    'start_at' => $mapped['start'],
                ]);
            }
            $localId = $task->id;
            $kind = 'task';
        } else {
            if ($localId && DB::table('prj_appointments')->where('id', $localId)->exists()) {
                DB::table('prj_appointments')->where('id', $localId)->update([
                    'title' => $mapped['title'],
                    'starts_at' => $mapped['start'],
                    'ends_at' => $mapped['end'],
                    'updated_at' => now(),
                ]);
            } else {
                $localId = DB::table('prj_appointments')->insertGetId([
                    'title' => $mapped['title'],
                    'starts_at' => $mapped['start'],
                    'ends_at' => $mapped['end'],
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $kind = 'appointment';
        }

        CalendarLink::query()->updateOrCreate(
            ['account_id' => $account->id, 'external_id' => $externalId],
            [
                'local_type' => $kind,
                'local_id' => $localId,
                'synced_at' => now(),
                'payload' => $raw,
            ]
        );
    }

    private function cancelLocal(CalendarAccount $account, string $externalId): void
    {
        $link = CalendarLink::query()->where('account_id', $account->id)->where('external_id', $externalId)->first();
        if (! $link) {
            return;
        }
        if ($link->local_type === 'appointment') {
            DB::table('prj_appointments')->where('id', $link->local_id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        }
        if ($link->local_type === 'task') {
            ProjectTask::query()->whereKey($link->local_id)->update(['status' => 'cancelled']);
        }
    }

    private function push(CalendarAccount $account): int
    {
        $pushed = 0;
        $appointments = DB::table('prj_appointments')->orderByDesc('id')->limit(100)->get();
        foreach ($appointments as $row) {
            $link = CalendarLink::query()
                ->where('account_id', $account->id)
                ->where('local_type', 'appointment')
                ->where('local_id', $row->id)
                ->first();
            if ($link && $link->synced_at && $row->updated_at && $link->synced_at->gte($row->updated_at)) {
                continue;
            }
            if ($row->status === 'cancelled') {
                continue;
            }
            $external = $this->upsertRemote($account, $link?->external_id, 'appointment', (int) $row->id, (string) $row->title, (string) $row->starts_at, (string) ($row->ends_at ?: $row->starts_at));
            if ($external) {
                CalendarLink::query()->updateOrCreate(
                    ['account_id' => $account->id, 'external_id' => $external],
                    ['local_type' => 'appointment', 'local_id' => $row->id, 'synced_at' => now()]
                );
                $pushed++;
            }
        }

        $tasks = ProjectTask::query()->whereNotNull('due_at')->orderByDesc('id')->limit(100)->get();
        foreach ($tasks as $task) {
            $link = CalendarLink::query()
                ->where('account_id', $account->id)
                ->where('local_type', 'task')
                ->where('local_id', $task->id)
                ->first();
            if ($link && $link->synced_at && $task->updated_at && $link->synced_at->gte($task->updated_at)) {
                continue;
            }
            $start = $task->start_at?->toIso8601String() ?? $task->due_at?->copy()->subHour()->toIso8601String();
            $end = $task->due_at?->toIso8601String();
            if (! $start || ! $end) {
                continue;
            }
            $external = $this->upsertRemote($account, $link?->external_id, 'task', (int) $task->id, (string) $task->title, $start, $end);
            if ($external) {
                CalendarLink::query()->updateOrCreate(
                    ['account_id' => $account->id, 'external_id' => $external],
                    ['local_type' => 'task', 'local_id' => $task->id, 'synced_at' => now()]
                );
                $pushed++;
            }
        }

        return $pushed;
    }

    private function upsertRemote(CalendarAccount $account, ?string $externalId, string $kind, int $localId, string $title, string $start, string $end): ?string
    {
        if ($account->provider === 'outlook') {
            $body = [
                'subject' => $title,
                'start' => ['dateTime' => $start, 'timeZone' => 'Asia/Tehran'],
                'end' => ['dateTime' => $end, 'timeZone' => 'Asia/Tehran'],
                'categories' => ['webina:'.$kind, 'webina-id:'.$localId],
            ];
            $request = Http::withToken((string) $account->access_token)->acceptJson()->timeout(20);
            $response = $externalId
                ? $request->patch('https://graph.microsoft.com/v1.0/me/events/'.$externalId, $body)
                : $request->post('https://graph.microsoft.com/v1.0/me/events', $body);

            return $response->successful() ? (string) ($response->json('id') ?: $externalId) : null;
        }

        $body = [
            'summary' => $title,
            'start' => ['dateTime' => $start],
            'end' => ['dateTime' => $end],
            'extendedProperties' => ['private' => ['webina_kind' => $kind, 'webina_id' => (string) $localId]],
        ];
        $base = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode((string) $account->calendar_id).'/events';
        $request = Http::withToken((string) $account->access_token)->acceptJson()->timeout(20);
        $response = $externalId
            ? $request->patch($base.'/'.$externalId, $body)
            : $request->post($base, $body);

        return $response->successful() ? (string) ($response->json('id') ?: $externalId) : null;
    }

    private function registerWatch(CalendarAccount $account): void
    {
        $channel = (string) Str::uuid();
        try {
            if ($account->provider === 'google') {
                $response = Http::withToken((string) $account->access_token)->timeout(15)->post(
                    'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode((string) $account->calendar_id).'/events/watch',
                    [
                        'id' => $channel,
                        'type' => 'web_hook',
                        'address' => url('/api/v1/integrations/calendars/webhook/google'),
                    ]
                );
                if ($response->successful()) {
                    $account->update([
                        'webhook_channel_id' => (string) ($response->json('id') ?: $channel),
                        'webhook_expires_at' => isset($response->json()['expiration'])
                            ? now()->createFromTimestampMs((int) $response->json('expiration'))
                            : now()->addDay(),
                    ]);
                }
            }
            if ($account->provider === 'outlook') {
                $secret = Str::random(24);
                $response = Http::withToken((string) $account->access_token)->timeout(15)->post(
                    'https://graph.microsoft.com/v1.0/subscriptions',
                    [
                        'changeType' => 'created,updated,deleted',
                        'notificationUrl' => url('/api/v1/integrations/calendars/webhook/outlook'),
                        'resource' => 'me/events',
                        'expirationDateTime' => now()->addDays(2)->toIso8601String(),
                        'clientState' => $secret,
                    ]
                );
                if ($response->successful()) {
                    $account->update([
                        'webhook_channel_id' => (string) $response->json('id'),
                        'webhook_secret' => $secret,
                        'webhook_expires_at' => now()->addDays(2),
                    ]);
                }
            }
        } catch (\Throwable) {
            // Polling still keeps the calendars aligned when webhooks are unreachable.
        }
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in?: int, email?: string}
     */
    private function exchangeGoogle(string $code): array
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => (string) config('integrations.google.client_id'),
            'client_secret' => (string) config('integrations.google.client_secret'),
            'redirect_uri' => $this->redirect('google'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Google token exchange failed.');
        }

        return $response->json();
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in?: int}
     */
    private function exchangeOutlook(string $code): array
    {
        $tenant = (string) config('integrations.microsoft.tenant', 'common');
        $response = Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
            'code' => $code,
            'client_id' => (string) config('integrations.microsoft.client_id'),
            'client_secret' => (string) config('integrations.microsoft.client_secret'),
            'redirect_uri' => $this->redirect('outlook'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Outlook token exchange failed.');
        }

        return $response->json();
    }

    private function refreshIfNeeded(CalendarAccount $account): void
    {
        if ($account->expires_at && $account->expires_at->isFuture()) {
            return;
        }
        if (! $account->refresh_token) {
            return;
        }
        if ($account->provider === 'google') {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('integrations.google.client_id'),
                'client_secret' => (string) config('integrations.google.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } else {
            $tenant = (string) config('integrations.microsoft.tenant', 'common');
            $response = Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
                'client_id' => (string) config('integrations.microsoft.client_id'),
                'client_secret' => (string) config('integrations.microsoft.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        }
        if ($response->successful() && $response->json('access_token')) {
            $account->update([
                'access_token' => (string) $response->json('access_token'),
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            ]);
            $account->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $when
     */
    private function googleWhen(array $when): string
    {
        if (! empty($when['dateTime'])) {
            return (string) $when['dateTime'];
        }
        if (! empty($when['date'])) {
            return (string) $when['date'].'T00:00:00+03:30';
        }

        return now()->toIso8601String();
    }

    private function redirect(string $provider): string
    {
        $configured = $provider === 'outlook'
            ? (string) config('integrations.microsoft.redirect')
            : (string) config('integrations.google.redirect');

        return $configured !== '' ? $configured : url('/api/v1/integrations/calendars/callback/'.$provider);
    }

    private function stateKey(string $state): string
    {
        return 'cal_oauth:'.$state;
    }
}
