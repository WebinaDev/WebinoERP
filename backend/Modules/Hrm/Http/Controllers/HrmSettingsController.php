<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Support\HrmAccess;
use Modules\Hrm\Support\HrmNotifier;

/** HR settings that are not payroll rates: notification channels and per-event toggles. */
class HrmSettingsController extends Controller
{
    public function notifications(): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $s = HrmNotifier::settings();
        $smsConfigured = false;
        if (class_exists(\Modules\Integrations\Services\ModirPayamakEdgeClient::class)) {
            try {
                $smsConfigured = (bool) app(\Modules\Integrations\Services\ModirPayamakEdgeClient::class)->isConfigured();
            } catch (\Throwable) {
                $smsConfigured = false;
            }
        }

        return response()->json(['data' => [
            'enabled' => $s['enabled'],
            'channels' => $s['channels'],
            'events' => $s['events'],
            'telegram_token_set' => $s['telegram_bot_token'] !== '',
            'telegram_env_token' => (string) config('integrations.telegram.token', '') !== '',
            'bale_token_set' => $s['bale_bot_token'] !== '',
            'bale_integration_token' => $s['bale_bot_token'] === '' && HrmNotifier::baleToken() !== '',
            'sms_configured' => $smsConfigured,
            'available_channels' => HrmNotifier::CHANNELS,
            'available_events' => HrmNotifier::EVENTS,
        ]]);
    }

    public function notificationsSave(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'channels' => 'required|array',
            'channels.*' => 'boolean',
            'events' => 'required|array',
            'events.*' => 'boolean',
            'telegram_bot_token' => 'nullable|string|max:200',
            'bale_bot_token' => 'nullable|string|max:200',
            'clear_telegram_token' => 'nullable|boolean',
            'clear_bale_token' => 'nullable|boolean',
        ]);
        $channels = [];
        foreach (HrmNotifier::CHANNELS as $c) {
            $channels[$c] = (bool) ($data['channels'][$c] ?? false);
        }
        $events = [];
        foreach (HrmNotifier::EVENTS as $e) {
            $events[$e] = (bool) ($data['events'][$e] ?? false);
        }
        $this->put('external_notifications_enabled', (bool) $data['enabled']);
        $this->put('notify_channels', $channels);
        $this->put('notify_events', $events);
        foreach (['telegram', 'bale'] as $bot) {
            if (! empty($data['clear_'.$bot.'_token'])) {
                $this->put($bot.'_bot_token', '');
            } elseif (! empty($data[$bot.'_bot_token'])) {
                $this->put($bot.'_bot_token', trim((string) $data[$bot.'_bot_token']));
            }
        }

        return $this->notifications();
    }

    public function notificationsTest(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate(['employee_id' => 'required|exists:hrm_employees,id']);
        $results = HrmNotifier::fanoutExternal(
            (int) $data['employee_id'],
            'test',
            'آزمایش اعلان منابع انسانی',
            'این پیام آزمایشی از تنظیمات منابع انسانی ارسال شده است.',
            true
        );

        return response()->json(['data' => ['results' => $results]]);
    }

    /** Employee: own Bale / Telegram chat ids. */
    public function myChannels(): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee, 404, 'No employee profile linked to this user');
        $profile = HrmEmployeeProfile::query()->where('employee_id', $employee->id)->first();
        $s = HrmNotifier::settings();

        return response()->json(['data' => [
            'bale_chat_id' => $profile?->bale_chat_id,
            'telegram_chat_id' => $profile?->telegram_chat_id,
            'mobile' => $employee->mobile,
            'channels' => $s['channels'],
        ]]);
    }

    public function myChannelsSave(Request $request): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee, 404, 'No employee profile linked to this user');
        $data = $request->validate([
            'bale_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^-?[0-9A-Za-z_@]+$/'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^-?[0-9A-Za-z_@]+$/'],
        ]);
        $profile = HrmEmployeeProfile::query()->firstOrNew(['employee_id' => $employee->id]);
        $profile->bale_chat_id = $data['bale_chat_id'] ?? null;
        $profile->telegram_chat_id = $data['telegram_chat_id'] ?? null;
        $profile->save();

        return $this->myChannels();
    }

    private function put(string $key, mixed $value): void
    {
        HrmPayrollSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
