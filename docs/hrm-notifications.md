# HR notifications (in-app, SMS, Bale, Telegram)

Settings UI: **مدیریت منابع انسانی ← تنظیمات اعلان منابع انسانی** (`/dashboard/hrm/hr-settings`).
API: `GET/POST /api/v1/hrm/settings/notifications`, test send `POST /api/v1/hrm/settings/notifications/test {employee_id}`.

* A master switch plus per-channel toggles (`in_app`, `sms`, `bale`, `telegram`).
  The default is in-app only.
* Per-event toggles: `leave_decision`, `shift_assigned`, `payslip_issued`, `payroll_paid`, `request_decision`, `missing_docs`, `offboarding`.
* Leave approve/reject, shift assignment, payslip issue and offboarding start call `HrmNotifier`.
  Every notice is stored in `hrm_notices`.
* **SMS** goes through the Integrations `NotificationFanout` and the configured provider (ModirPayamak).
  The employee needs a mobile number.
* **Bale / Telegram**: a direct `sendMessage` to the employee's chat ID.
  Employees enter their chat IDs on the portal page *همگام‌سازی تقویم و اعلان* (`PUT /api/v1/hrm/me/notification-channels`).
  Bot tokens are looked up in this order:
  1. The HR settings (write-only; never returned by the API).
  2. The Bale integration token, or `TELEGRAM_BOT_TOKEN` / `integrations.bale.token`.
* Group chat bridges are not used, so personal payroll and leave messages are never posted to groups.

Nothing is sent externally until a bot token / SMS provider and the employee chat ID or mobile are set.
The test result shows the reason per channel (e.g. `skipped_no_chat_id`).
