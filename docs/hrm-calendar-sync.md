# HR calendar feeds (ICS)

Portal page: **پورتال من ← همگام‌سازی تقویم و اعلان** (`/dashboard/hrm/my-calendar`).

* `GET /api/v1/hrm/me/calendar/feed` returns `{token, feed_url, webcal_url, google_url, outlook_url}`. It creates the token on first use.
* `POST /api/v1/hrm/me/calendar/feed/regenerate` issues a new token. The old URL returns 404 right away.
* `GET /api/v1/hrm/me/calendar.ics?lang=fa|en` downloads the feed for a signed-in user.
* `GET /api/v1/hrm/calendar/employees/{id}/ics` lets HR download an employee's calendar.
* Public feed (no login, throttled): `GET /api/v1/hrm/calendar/feeds/{token}.ics`.

The feed holds approved leave (all-day events) and planned shifts (timed events; night shifts run into the next day).
It also includes a `VTIMEZONE` for Asia/Tehran (+03:30) and folds lines per RFC 5545.

## Subscribe

* **Google Calendar**: use the "Add to Google Calendar" button.
  Or go to Other calendars → *From URL* and paste `feed_url`. Google refreshes about every 8–24 h.
* **Outlook / Microsoft 365**: use the "Add to Outlook" button.
  Or go to Add calendar → *Subscribe from web* and paste `feed_url`.
* **Apple Calendar / Thunderbird**: open `webcal_url`.

The token URL is a secret. Anyone with it can read that employee's leave and shifts, so regenerate it if it leaks.
Sync is read-only (subscription). Nothing is pushed through Google or Microsoft OAuth.
