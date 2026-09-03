# گزارش اودیت جامع WebinoERP

تاریخ: ۱۴۰۵/۰۶/۱۲ (۲۰۲۶-۰۹-۰۳)
دامنه بررسی: `backend/` (Laravel 11 + Sanctum + nwidart/laravel-modules)، `frontend/` (Next.js App Router)، `docker/`، `docker-compose*.yml`، `install.sh`

راهنمای شدت:
- 🔴 **CRITICAL** — سوءاستفاده مستقیم، نشت داده یا اجرای کد. قبل از هر استقرار عمومی باید حل شود.
- 🟠 **HIGH** — دور زدن کنترل دسترسی یا نقص امنیتی جدی.
- 🟡 **MEDIUM** — نقص منطقی/سخت‌سازی که در ترکیب با موارد دیگر خطرناک می‌شود.
- 🔵 **LOW** — بهبود کیفیت، پایداری یا سخت‌سازی دفاعی.

---


---

## وضعیت رفع (فاز ۷)

تاریخ به‌روزرسانی: ۲۰۲۶-۰۹-۰۳

همهٔ **۴۷ مورد** شناسایی‌شده در این اودیت (۴۵ مورد اصلی + ۵.۷ و ۵.۸) در هفت فاز remediation رفع شدند.

| فاز | دامنه | وضعیت |
|---|---|---|
| ۰ | ایمن‌سازی پیکربندی / Docker / Caddy / لاگ OTP / SSH key hidden | ✅ |
| ۱ | RCE ترمینال، OTP abuse، webhook، throttle عمومی | ✅ |
| ۲ | fail-closed مجوز، 2FA، نشست، CSRF لایه دوم، is_active | ✅ |
| ۳ | FormRequest / validated input، revalidate سایت | ✅ |
| ۴ | تاریخ جلالی (تبدیل میلادی)، حذف moment-jalaali، use-intl | ✅ |
| ۵ | RTL واحد، کلاس منطقی، dir روی Radix، آیکون‌های جهت‌دار | ✅ |
| ۶ | استخراج i18n — ۰ رشته فارسی هاردکد در `*.tsx` | ✅ |
| ۷ | تأیید و بستن سند | ✅ |

### موارد جدید ثبت‌شده در فاز ۵

### ✅ 🔵 ۵.۷ — نبود `dir` روی کامپوننت‌های Radix (به‌جز popover) — **حل‌شده**

`dropdown-menu`، `select`، `tooltip`، `sheet`، `tabs`، `scroll-area` و `alert-dialog` اکنون `dir={useTextDirection()}` دارند (هم‌الگو با `popover`).

### ✅ 🔵 ۵.۸ — آیکون‌های Chevron/Arrow بدون شرط جهت — **حل‌شده**

آیکون‌های جهت‌دار با `rtl:rotate-180` (یا معادل منطقی) هم‌راستا شدند.

### معیارهای پذیرش بازبینی‌شده

- `APP_DEBUG=false` از مسیر نصب production (`install.sh` + `docker-compose.prod.yml`)
- Redis با `--requirepass`؛ هدرهای امنیتی و TLS در Caddy
- ترمینال پلتفرم پشت `role:system_manager` و پرچم `PLATFORM_ALLOW_LOCAL_EXEC`
- OTP: بدون ساخت خودکار کاربر؛ `hash_equals`؛ throttle تفکیک‌شده؛ بدون لاگ کد
- `EnforceModulePermission` fail-closed
- دیت‌پیکر جلالی ↔ ISO میلادی از طریق `react-date-object`
- شمارش رشته فارسی در `frontend/src/**/*.tsx`: **۰**

---

## خلاصه اجرایی

| بخش | 🔴 | 🟠 | 🟡 | 🔵 |
|---|---|---|---|---|
| احراز هویت و نشست | 3 | 4 | 3 | 2 |
| کنترل دسترسی (Authorization) | 1 | 2 | 2 | 1 |
| زیرساخت / Docker / شبکه | 2 | 3 | 3 | 1 |
| Webhook و نقاط عمومی | 1 | 1 | 2 | 0 |
| RTL/LTR و بین‌المللی‌سازی | 0 | 1 | 3 | 2 |
| تاریخ جلالی و دیت‌پیکر | 2 | 2 | 3 | 1 |
| **جمع (قبل از رفع)** | **9** | **13** | **16** | **7** |
| **باقی‌مانده باز** | **0** | **0** | **0** | **0** |

چهار ریسک اصلی که در فازهای ۰–۱ حل شدند (برای مرجع تاریخی):

1. **RCE از طریق ترمینال پلتفرم** — `POST /api/v1/platform/servers/{id}/terminal` دستور دلخواه را داخل کانتینر backend اجرا می‌کند (بخش ۲.۱).
2. **`APP_DEBUG=true` و `APP_ENV=local` در همان compose‌ای که `install.sh` روی سرور production اجرا می‌کند** — صفحه خطای Laravel کل `.env` شامل `APP_KEY` و رمز دیتابیس را افشا می‌کند (بخش ۳.۱).
3. **کد OTP ورود در لاگ متنی نوشته می‌شود** — هرکس دسترسی خواندن لاگ داشته باشد می‌تواند به هر حساب وارد شود (بخش ۱.۱).
4. **دیت‌پیکر جلالی، تاریخ جلالی را به‌جای میلادی به API می‌فرستد** — تنها باگ غیرامنیتی در این فهرست، اما چون روی فاکتور، چک و رسید حسابداری اثر می‌گذارد، داده مالی را خراب می‌کند (بخش ۶.۱).

---

## ۱) احراز هویت و مدیریت نشست

### ✅ 🔴 ۱.۱ — کد OTP ورود در لاگ ذخیره می‌شود — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthParityController.php:29-32`

```php
Log::info('auth.otp.login.generated', [
    'mobile' => $data['mobile'],
    'code' => $code,          // ← کد ورود در storage/logs/laravel.log
]);
```

`POST /api/v1/auth/otp/send` عمومی است. مهاجم شماره قربانی را می‌فرستد، سپس کد را از لاگ می‌خواند (نشت لاگ، دسترسی خواندن فایل، ارسال لاگ به سرویس بیرونی، یا افشا از طریق مشکل ۳.۱) و بدون داشتن سیم‌کارت وارد می‌شود.

**راه‌حل:** لاگ کد را کامل حذف کنید. فقط hash کد یا یک شناسه درخواست را لاگ کنید. کد را در Cache به‌صورت `Hash::make($code)` نگه دارید و در verify با `Hash::check` مقایسه کنید.

### ✅ 🔴 ۱.۲ — ثبت‌نام خودکار کاربر در مسیر تأیید OTP — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthParityController.php:79-88`

```php
$user = User::query()->where('phone', $data['mobile'])->first();
if (! $user) {
    $user = User::query()->create([                      // ← ساخت کاربر بدون هیچ تأییدی
        'email' => 'u'.$data['mobile'].'@phone.local',
        ...
    ]);
    $user->assignRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
}
```

مسیر «تأیید OTP» عملاً یک نقطه ثبت‌نام باز است: هر شماره‌ای که OTP بگیرد، حساب فعال با نقش `client` می‌سازد. ترکیب با ۱.۱ یعنی ساخت حساب دلخواه، و ترکیب با ۲.۲ یعنی این حساب به ماژول‌های نگاشت‌نشده هم دسترسی دارد. ایمیل تولیدی (`u09...@phone.local`) هم فضای ایمیل کاربران واقعی را آلوده می‌کند و می‌تواند با `unique:users,email` تعارض بسازد.

**راه‌حل:** ساخت کاربر را از verify جدا کنید. اگر شماره ثبت‌نام‌شده نیست، `404` برگردانید. ثبت‌نام را به یک مسیر جداگانه با پرچم تنظیمات (`ALLOW_SELF_REGISTRATION`) منتقل کنید.

### ✅ 🔴 ۱.۳ — احراز هویت دومرحله‌ای هیچ‌جا اعمال نمی‌شود — **حل‌شده**

`backend/Modules/Core/Http/Controllers/TwoFactorController.php:19,37,48`

کلید `2fa:verified:{id}` فقط داخل همین کنترلر نوشته و خوانده می‌شود. جست‌وجوی کل پروژه هیچ middleware یا گاردی پیدا نکرد که آن را بررسی کند:

```
Modules/Core/Http/Controllers/TwoFactorController.php:19   Cache::get('2fa:verified:'.$user->id, false)   ← فقط نمایش وضعیت
Modules/Core/Http/Controllers/TwoFactorController.php:37   Cache::put('2fa:verified:'.$user->id, true, ...)
```

یعنی 2FA کاملاً تشریفاتی است؛ توکن Sanctum قبل از 2FA صادر می‌شود و همه مسیرها با همان توکن باز هستند.

علاوه بر این، `send()` (خط ۴۲-۵۳) کد را **هیچ‌جا ارسال نمی‌کند** — نه SMS نه ایمیل. فقط در `local` داخل پیام پاسخ برمی‌گردد. در production کاربر هرگز کد را نمی‌گیرد.

**راه‌حل:** یک middleware `require.2fa` بسازید که برای نقش‌های حساس وجود `2fa:verified` را الزامی کند و آن را روی گروه مسیرهای admin بگذارید. توکن پیش از 2FA را با ability محدود (`['2fa-pending']`) صادر کنید و پس از تأیید ارتقا دهید. برای `send()` واقعاً کد را از طریق SMS/ایمیل بفرستید.

### ✅ 🟠 ۱.۴ — انتقال توکن احراز هویت روی HTTP بدون رمزنگاری — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthController.php:146`، `backend/.env.example:34`، `docker/caddy/Caddyfile:7`

```php
$secure = (bool) config('session.secure', false);   // پیش‌فرض false
```

Caddy فقط روی `:80` گوش می‌دهد و `SESSION_SECURE_COOKIE=false` است. کوکی `webino_auth_token` که یک توکن Sanctum کامل با ability `*` است، روی HTTP ساده منتقل می‌شود. روی سرور واقعی (`http://IP:3080`) کل ترافیک شامل توکن قابل شنود است.

**راه‌حل:** یک بلوک TLS در Caddyfile با `tls` خودکار برای دامنه اضافه کنید، `SESSION_SECURE_COOKIE=true` را در `docker-compose.prod.yml` تنظیم کنید و ریدایرکت `:80 → :443` بگذارید.

### ✅ 🟠 ۱.۵ — CSRF برای همه `api/*` غیرفعال است در حالی که احراز هویت کوکی‌محور است — **حل‌شده**

`backend/bootstrap/app.php:48-51`

```php
$middleware->validateCsrfTokens(except: [
    'api/*',
    'sanctum/csrf-cookie',
]);
```

`AuthenticateFromCookie` کوکی را به هدر `Authorization` تبدیل می‌کند، پس مرورگر بدون دخالت جاوااسکریپت درخواست را احراز هویت‌شده می‌فرستد. تنها دفاع باقی‌مانده `SameSite=Lax` است (`AuthController.php:157`). Lax درخواست POST بین‌سایتی را می‌بندد اما ناوبری GET سطح بالا را نمی‌بندد؛ هر مسیر GET که تغییر حالت بدهد قابل سوءاستفاده است و هیچ دفاع لایه‌دومی وجود ندارد.

**راه‌حل:** `SameSite=Strict` کنید و یک middleware سبک بگذارید که برای متدهای غیرایمن وجود هدر سفارشی (مثل `X-Requested-With: XMLHttpRequest`) را الزامی کند — این هدر را مرورگر در درخواست بین‌سایتی ساده نمی‌فرستد.

### ✅ 🟠 ۱.۶ — تغییر رمز عبور بدون نیاز به رمز فعلی — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthParityController.php:108-117`

```php
public function setPassword(Request $request): JsonResponse
{
    $request->validate(['password' => 'required|string|min:8|confirmed']);
    $user = $request->user();
    $user->update(['password' => Hash::make($request->input('password'))]);
```

هیچ `current_password` گرفته نمی‌شود و توکن‌های دیگر باطل نمی‌شوند. اگر توکنی لو برود (مثلاً از طریق ۱.۴)، مهاجم رمز را عوض کرده و مالکیت دائمی حساب را می‌گیرد.

**راه‌حل:** `'current_password' => ['required', 'current_password']` را اضافه کنید و پس از تغییر، `$user->tokens()->where('id','!=',$current->id)->delete()` را اجرا کنید.

### ✅ 🟠 ۱.۷ — محدودیت نرخ روی مسیرهای احراز هویت عملاً کار نمی‌کند — **حل‌شده**

`backend/app/Providers/AppServiceProvider.php:48-50` + عدم وجود TrustProxies

```php
RateLimiter::for('auth-public', function (Request $request) {
    return Limit::perMinute(20)->by($request->ip());
});
```

جست‌وجوی `TrustProxies|X-Forwarded` در کل backend **هیچ نتیجه‌ای** ندارد. چون Caddy معکوس‌پروکسی است، `$request->ip()` همیشه IP کانتینر Caddy را برمی‌گرداند. نتیجه:

- همه کاربران دنیا در یک سبد ۲۰ درخواست در دقیقه قرار می‌گیرند → **منع سرویس برای کاربران عادی**.
- مهاجم عملاً محدودیت اختصاصی ندارد و brute-force روی OTP شش‌رقمی ممکن می‌شود.
- `ip` ثبت‌شده روی توکن‌ها (`AuthParityController.php:93`) و لاگ‌های حسابرسی همه غلط است.

همچنین ۲۰ در دقیقه برای ارسال OTP بسیار سخاوتمندانه است و برای verify هیچ محدودیت اختصاصی‌تری وجود ندارد.

**راه‌حل:** `$middleware->trustProxies(at: '*')` (یا رنج شبکه Docker) را در `bootstrap/app.php` اضافه کنید. سپس محدودیت‌ها را تفکیک کنید: ارسال OTP `3/min` بر اساس شماره موبایل، verify `5/min` بر اساس موبایل + IP، و پس از ۵ تلاش ناموفق کد را باطل کنید.

### ✅ 🟡 ۱.۸ — OTP فاقد شمارنده تلاش و مقایسه امن — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthParityController.php:72`، `260`، `TwoFactorController.php:32`

```php
if (! $expected || $expected !== $data['code']) {     // مقایسه غیرمقاوم به زمان
```

مقایسه با `!==` روی رشته راز، و مهم‌تر: کد پس از تلاش ناموفق **باطل نمی‌شود**. با پنجره ۵ دقیقه‌ای و ۱۰⁶ فضای کلید، بدون محدودیت مؤثر (۱.۷) حدس زدن عملی است.

**راه‌حل:** از `hash_equals` استفاده کنید، شمارنده تلاش در Cache نگه دارید و بعد از ۵ خطا `Cache::forget` کنید.

### ✅ 🟡 ۱.۹ — OTP ایمیل هرگز ارسال نمی‌شود — **حل‌شده**

`backend/Modules/Core/Http/Controllers/AuthParityController.php:239-251`

`sendEmailOtp` کد را در Cache می‌گذارد اما هیچ فراخوانی `Mail::` وجود ندارد. مسیر `verifyEmailOtp` هم فقط `{verified:true}` برمی‌گرداند و توکنی صادر نمی‌کند، پس در جریان واقعی بی‌استفاده است. یک قابلیت نیمه‌کاره که در production شکسته است.

**راه‌حل:** یا Mailable را پیاده کنید یا هر دو مسیر را از `Routes/api.php` حذف کنید تا سطح حمله بی‌دلیل باز نماند.

### ✅ 🟡 ۱.۱۰ — `is_active` در `$fillable` مدل User — **حل‌شده**

`backend/app/Models/User.php:34`

```php
protected $fillable = ['name', ..., 'password', 'is_active', 'last_login_at', 'avatar_path'];
```

به‌تنهایی مشکل نیست، اما هر کنترلری که `update($request->all())` یا `create([...$data])` انجام دهد اجازه فعال/غیرفعال‌سازی حساب را می‌دهد. الگوی `...$data` در پروژه رایج است (مثلاً `ServerController.php:36`, `SshKeyController.php:28`).

**راه‌حل:** `is_active` و `last_login_at` را از `$fillable` بردارید و فقط از طریق متد اختصاصی تغییر دهید.

### ✅ 🔵 ۱.۱۱ — نبود `$hidden` روی مدل کلید SSH — **حل‌شده**

`backend/Modules/Platform/Entities/PlatformSshKey.php`

کنترلر فعلی ستون‌ها را صریح انتخاب می‌کند (`SshKeyController.php:13,32`) پس نشتی وجود ندارد، اما مدل `$hidden` ندارد و cast `encrypted` هنگام سریال‌سازی، کلید را **رمزگشایی‌شده** بیرون می‌دهد. هر `return $key;` جدید در آینده کلید خصوصی را افشا می‌کند.

**راه‌حل:** `protected $hidden = ['private_key'];` اضافه کنید.

### ✅ 🔵 ۱.۱۲ — نبود ابطال توکن در تغییر وضعیت کاربر — **حل‌شده**

هنگام غیرفعال شدن کاربر (`is_active = false`)، توکن‌های Sanctum او پاک نمی‌شوند. `autoLogin` مقدار `is_active` را چک می‌کند (`AuthParityController.php:164`) اما مسیر معمول `auth:sanctum` این چک را ندارد؛ یعنی کاربر غیرفعال‌شده با توکن قبلی همچنان دسترسی دارد.

**راه‌حل:** یک middleware `EnsureUserIsActive` روی گروه `auth:sanctum` بگذارید و در observer مدل User با تغییر `is_active` توکن‌ها را حذف کنید.

---

## ۲) کنترل دسترسی (Authorization)

### ✅ 🔴 ۲.۱ — اجرای کد دلخواه از طریق ترمینال پلتفرم (RCE) — **حل‌شده**

`backend/Modules/Platform/Http/Controllers/ServerController.php:185-189`
`backend/Modules/Platform/Services/SshExecutor.php:21-23, 124-128`
`backend/Modules/Platform/Routes/api.php:45`

```php
// ServerController.php
public function terminalExec(Request $request, PlatformServer $server): JsonResponse
{
    $cmd = $request->validate(['command' => 'required|string|max:2000'])['command'];
    return $this->ok($this->ssh->run($server, $cmd, 60));
}

// SshExecutor.php
if ($server->is_localhost || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true)) {
    return $this->local($command, $timeout);     // ← بدون SSH
}
...
protected function local(string $command, int $timeout): array
{
    $process = Process::fromShellCommandline($command);   // ← تفسیر کامل شل
```

زنجیره حمله کامل، با داشتن فقط مجوز `platform.servers.manage`:

```
1) POST /api/v1/platform/servers          {"name":"x","ip":"127.0.0.1","is_localhost":true}
2) POST /api/v1/platform/servers/{id}/terminal   {"command":"cat /var/www/html/.env"}
   → اجرای شل داخل کانتینر backend: APP_KEY، رمز DB، همه توکن‌های یکپارچه‌سازی
   → و از آنجا حرکت جانبی به postgres و redis روی شبکه داخلی Docker
```

`is_localhost` در `store` به‌عنوان `nullable|boolean` پذیرفته می‌شود (`ServerController.php:33`) پس مهاجم خودش این حالت را می‌سازد. توجه: مسیر SSH ریموت هم `$command` را به شل ریموت می‌دهد، پس ماهیتاً یک ابزار RCE است — مشکل اینجاست که با مجوز ماژولی معمولی و بدون هیچ لایه دومی باز است.

**راه‌حل:**
- این مسیر را از مجوز ماژولی جدا کنید: `->middleware('role:system_manager')` به‌علاوه الزام 2FA فعال (پس از حل ۱.۳).
- ساخت سرور با `is_localhost=true` را ممنوع کنید یا فقط به `system_manager` بدهید و در `SshExecutor::run` مسیر `local()` را با یک پرچم صریح `PLATFORM_ALLOW_LOCAL_EXEC=false` پیش‌فرض ببندید.
- در `local()` از `new Process([...])` با آرگومان‌های آرایه‌ای استفاده کنید، نه `fromShellCommandline`.
- همه اجراها را در `CoreInfraAuditLog` ثبت کنید.

### ✅ 🟠 ۲.۲ — `EnforceModulePermission` در حالت شک، اجازه می‌دهد (fail-open) — **حل‌شده**

`backend/Modules/Core/Http/Middleware/EnforceModulePermission.php:47-63`

```php
$map = config("module_permissions.{$module}", []);
if ($map === []) {
    return null;              // ← هیچ مجوزی لازم نیست
}
...
$rules = $map[$segment] ?? $map['*'] ?? null;
if (! $rules) {
    return null;              // ← هیچ مجوزی لازم نیست
}
```

و در `handle`:

```php
if ($permission && ! $user->can($permission)) { return 403; }
return $next($request);       // ← permission = null یعنی عبور آزاد
```

هر ماژول یا هر segment که در `config/module_permissions.php` نگاشت نشده باشد، برای **هر کاربر احراز هویت‌شده** (شامل حساب `client` که خودش از طریق ۱.۲ ساخته) باز است. این یک خطای طراحی است: پیش‌فرض باید «رد» باشد نه «قبول». الان ۱۴ ماژول نگاشت دارند و همه `'*'` دارند، اما هر ماژول جدید یا هر تغییر نام کلید بی‌صدا کل ماژول را باز می‌کند.

**راه‌حل:** پیش‌فرض را برعکس کنید — اگر نگاشت پیدا نشد `403` بدهید و در محیط `local` یک لاگ هشدار بنویسید تا نگاشت‌های جاافتاده پیدا شوند.

### ✅ 🟠 ۲.۳ — عدم تطابق پیشوند مسیر با کلید مجوز، نگاشت‌های دقیق را بی‌اثر می‌کند — **حل‌شده**

`backend/Modules/Core/Http/Middleware/EnforceModulePermission.php:52-58`

```php
$prefix = "api/v1/{$module}/";                    // "api/v1/site_builder/"
$relative = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
$segment = explode('/', $relative)[0] ?: '*';
```

دو ماژول پیشوند URL متفاوت از کلید مجوز دارند:

| ثبت مسیر | پیشوند URL | کلید مجوز | segment محاسبه‌شده |
|---|---|---|---|
| `SiteBuilderServiceProvider.php:36-37` | `api/v1/site-builder` | `site_builder` | `"api"` → سقوط به `'*'` |
| `PlatformServiceProvider` (گروه forms) | `api/v1/forms` | `platform` | `"api"` → سقوط به `'*'` |

نتیجه مشخص: در `config/module_permissions.php:130` مسیرهای `provisions` باید با `site_builder.provision.manage` کنترل شوند، اما چون segment همیشه `api` می‌شود و به `'*'` می‌افتد، با `site_builder.catalog.manage` کنترل می‌شوند (خط ۱۳۱). یعنی هر کسی که مجوز مدیریت **کاتالوگ** دارد می‌تواند **provision** انجام دهد.

**راه‌حل:** به‌جای بازسازی رشته پیشوند، از `$request->route()->getPrefix()` استفاده کنید یا پیشوند را به‌عنوان پارامتر دوم middleware پاس دهید: `module.permission:site_builder,site-builder`.

### ✅ 🟡 ۲.۴ — `Modules/Scm` با `$request->all()` مستقیم به سرویس — **حل‌شده**

`backend/Modules/Scm/Http/Controllers/ScmWarehouseController.php:28, 69, 96, 123, 130`

```php
$data = $this->warehouse->updateWarehouse(['id' => $warehouse->id, ...$request->all()]);
$data = $this->warehouse->createDocument($request->all(), 'inbound', $request->user()?->id);
$data = $this->warehouse->createAudit($request->all(), $request->user()?->id);
```

هیچ `validate()` انجام نمی‌شود و ورودی خام به لایه سرویس/مدل می‌رود. برای یک ماژول انبار (اسناد ورود/خروج و انبارگردانی) این یعنی امکان دستکاری فیلدهایی مثل `status`، `created_by`، `warehouse_id` یا تاریخ سند.

**راه‌حل:** برای هر متد یک FormRequest بنویسید و فقط `$request->validated()` را پاس دهید.

### ✅ 🟡 ۲.۵ — پذیرش `$request->all()` در مسیرهای parity حسابداری — **حل‌شده**

`backend/Modules/Accounting/Http/Controllers/WarehouseAjaxParityController.php:38`
`backend/Modules/Accounting/Services/AccountingWpActionService.php:31`
`backend/Modules/Integrations/Http/Controllers/WebinocrmBaleRestController.php:27, 76, 96, 135`

همان الگو: ورودی خام به سرویس. برای ماژول حسابداری (اسناد مالی) این بالاترین اولویت بعد از SCM است.

### ✅ 🔵 ۲.۶ — `SshKeyController` سیاست ندارد — **حل‌شده**

`backend/Modules/Platform/Http/Controllers/SshKeyController.php:35`

`destroy` هیچ بررسی مالکیتی ندارد؛ هر کاربر با `platform.servers.manage` می‌تواند کلید SSH ساخته‌شده توسط دیگری را حذف کند و سرورهای وابسته را از کار بیندازد (`ssh_key_id` با `nullOnDelete` تنظیم شده).

---

## ۳) زیرساخت، Docker و شبکه

### ✅ 🔴 ۳.۱ — `APP_DEBUG=true` و `APP_ENV=local` در compose پیش‌فرض که روی سرور اجرا می‌شود — **حل‌شده**

`docker-compose.yml:45-46, 89, 122, 155`، `install.sh:324`، `backend/.env.example:2-4`

```yaml
# docker-compose.yml — همان فایلی که install.sh استفاده می‌کند
APP_ENV: local
APP_DEBUG: "true"
```

`install.sh` هیچ‌گاه `docker-compose.prod.yml` را اعمال نمی‌کند (که درست تنظیم شده: `APP_ENV: production`, `APP_DEBUG: "false"`). پس نصب یک‌خطی روی سرور عمومی، Laravel را در حالت debug بالا می‌آورد:

- صفحه خطای Ignition کل `.env` را نمایش می‌دهد: `APP_KEY`، `DB_PASSWORD`، `BALE_BOT_TOKEN`، `MODIRPAYAMAK_API_KEY`.
- افشای `APP_KEY` یعنی امکان جعل کوکی‌های امضاشده و رمزگشایی مقادیر `encrypted` (شامل کلیدهای خصوصی SSH در `platform_ssh_keys`).
- Telescope هم در `local` ثبت می‌شود (`AppServiceProvider.php:41-43`) و در صورت نصب، تاریخچه کامل درخواست‌ها و کوئری‌ها را بدون احراز هویت باز می‌کند.

**راه‌حل:** در `install.sh` مقادیر `APP_ENV=production` و `APP_DEBUG=false` را در `backend/.env` بنویسید و `-f docker-compose.yml -f docker-compose.prod.yml` را به تمام فراخوانی‌های `compose_cli` اضافه کنید. `APP_DEBUG=false` را در `.env.example` پیش‌فرض کنید.

### ✅ 🔴 ۳.۲ — اسرار پیش‌فرض ثابت در مخزن — **حل‌شده**

`backend/.env.example:54-56`، `.env.example` ریشه

```
REVERB_APP_ID=webino
REVERB_APP_KEY=webino-key
REVERB_APP_SECRET=webino-secret
POSTGRES_PASSWORD=postgres
DB_PASSWORD=postgres
```

`install.sh` این مقادیر را بازتولید نمی‌کند، پس هر نصبی همین اسرار عمومی را دارد. با `REVERB_APP_SECRET` معلوم، مهاجم می‌تواند روی کانال‌های broadcast پیام جعلی منتشر کند. رمز `postgres` هم اگر پورت ۵۴۳۲ به هر شکلی expose شود، دسترسی کامل دیتابیس است.

**راه‌حل:** در `install.sh` همان‌طور که `APP_KEY` را با `openssl` می‌سازید، `POSTGRES_PASSWORD`، `REDIS_PASSWORD` و `REVERB_APP_SECRET` را هم تصادفی تولید و در `.env` بنویسید. مقادیر `.env.example` را به `CHANGE_ME` تغییر دهید.

### ✅ 🟠 ۳.۳ — نبود TrustProxies — **حل‌شده**

`backend/bootstrap/app.php` — جست‌وجوی `trustProxies` هیچ نتیجه‌ای ندارد.

پیامدها در ۱.۷ توضیح داده شد (شکست کامل rate limiting و لاگ IP). علاوه بر آن، بدون اعتماد به `X-Forwarded-Proto`، متد `$request->isSecure()` همیشه `false` است، پس تولید URL مطلق (لینک‌های ایمیل، `asset()`، ریدایرکت‌های OAuth) با `http://` ساخته می‌شود حتی اگر بعداً TLS اضافه شود.

**راه‌حل:**

```php
$middleware->trustProxies(at: '*', headers:
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO
);
```

### ✅ 🟠 ۳.۴ — هیچ هدر امنیتی در Caddy تنظیم نشده — **حل‌شده**

`docker/caddy/Caddyfile` — جست‌وجوی `Strict-Transport|X-Frame-Options|X-Content-Type|Content-Security-Policy|Referrer-Policy` صفر نتیجه دارد.

نبود `X-Frame-Options`/`frame-ancestors` یعنی امکان clickjacking روی داشبورد؛ نبود `X-Content-Type-Options: nosniff` یعنی ریسک MIME sniffing روی فایل‌های آپلودی؛ نبود `Referrer-Policy` یعنی نشت مسیرهای داخلی به سایت‌های بیرونی.

**راه‌حل:** یک بلوک `header` سراسری در Caddyfile اضافه کنید:

```
header {
    Strict-Transport-Security "max-age=31536000; includeSubDomains"
    X-Content-Type-Options "nosniff"
    X-Frame-Options "SAMEORIGIN"
    Referrer-Policy "strict-origin-when-cross-origin"
    -Server
}
```

### ✅ 🟠 ۳.۵ — `config/cors.php` وجود ندارد — **حل‌شده**

فایل منتشر نشده، پس پیش‌فرض Laravel اعمال می‌شود: `allowed_origins => ['*']`. الان چون `supports_credentials` پیش‌فرض `false` است سوءاستفاده مستقیم ندارد، اما تنظیمات CORS برای یک ERP باید صریح و بسته باشد، نه ضمنی و باز.

**راه‌حل:** `php artisan config:publish cors` و محدود کردن `allowed_origins` به `APP_URL` با `supports_credentials => true`.

### ✅ 🟡 ۳.۶ — Redis بدون رمز عبور — **حل‌شده**

`docker-compose.yml:21-26`، `backend/.env.example:46` (`REDIS_PASSWORD=null`)

Redis بدون `requirepass` بالا می‌آید. Redis اکنون هم Cache است هم `SESSION_DRIVER` هم صف. هر کد اجراشده روی شبکه Docker (مثلاً از طریق ۲.۱) می‌تواند نشست‌ها، کدهای OTP در Cache و توکن‌های `auto_login` را بخواند یا جعل کند.

**راه‌حل:** `command: redis-server --requirepass ${REDIS_PASSWORD}` و تولید رمز تصادفی در `install.sh`.

### ✅ 🟡 ۳.۷ — `chmod -R 777` روی storage در نصب — **حل‌شده**

`install.sh` (بخش «Ensuring Laravel storage & cache directories»)

دسترسی نوشتن برای همه کاربران سیستم روی `storage/` و `bootstrap/cache/`. با توجه به این‌که لاگ‌ها حاوی کد OTP هستند (۱.۱) و `bootstrap/cache` فایل PHP اجراشدنی نگه می‌دارد، این یک مسیر ارتقای دسترسی محلی است.

**راه‌حل:** `chmod -R 775` با `chown -R` به UID کاربر کانتینر (`www-data`).

### ✅ 🟡 ۳.۸ — دو پکیج فرانت‌اند اعلام‌نشده استفاده می‌شوند — **حل‌شده**

`frontend/package.json` — این دو import شده‌اند اما در `dependencies` نیستند:

```
use-intl   (۱ فایل)   ← فقط به‌صورت transitive از next-intl می‌آید
moment     (۱ فایل)   ← فقط به‌صورت transitive از moment-jalaali می‌آید
```

با هر ارتقای `next-intl` یا `moment-jalaali` که این وابستگی‌ها را کنار بگذارد، بیلد فرانت‌اند بی‌مقدمه می‌شکند.

**راه‌حل:** هر دو را صریحاً به `dependencies` اضافه کنید یا importها را به پکیج والد تغییر دهید.

### ✅ 🔵 ۳.۹ — تعریف تکراری و ناهمگون پورت — **حل‌شده**

`.env.example` ریشه از `NGINX_HTTP_PORT`/`NGINX_HTTPS_PORT` نام می‌برد، ولی `docker-compose.yml:192-193` از `WEB_HTTP_PORT`/`WEB_HTTPS_PORT` استفاده می‌کند. تنظیم متغیر مستندشده هیچ اثری ندارد.

---

## ۴) Webhook‌ها و نقاط عمومی

### ✅ 🔴 ۴.۱ — Webhook بله بدون هیچ اعتبارسنجی، ورودی خام را لاگ می‌کند — **حل‌شده**

`backend/Modules/Integrations/Http/Controllers/BaleIntegrationController.php:53-58`

```php
public function webhook(Request $request): JsonResponse
{
    Log::channel('single')->info('bale.webhook', $request->all());
    return response()->json(['ok' => true]);
}
```

متغیر `BALE_WEBHOOK_SECRET` در `.env.example:79` تعریف شده اما **هیچ‌جا استفاده نمی‌شود**. نتیجه: یک مسیر عمومی که هر بدنه‌ای را بدون محدودیت اندازه در لاگ می‌نویسد. مهاجم با ارسال حجم بالا دیسک سرور را پر می‌کند (منع سرویس روی همه سرویس‌ها از جمله postgres) و می‌تواند رکورد لاگ جعلی تزریق کند.

مقایسه کنید با `WebinocrmGitWebhookController.php:23, 33-38` که **درست** پیاده شده (`hash_equals` + HMAC-SHA256 روی بدنه خام) — همان الگو باید اینجا اعمال شود. `TelegramIntegrationController.php:36` نیز عیناً همین مشکل را دارد.

**راه‌حل:** امضا را با `hash_equals(hash_hmac('sha256', $request->getContent(), config('integrations.bale.webhook_secret')), $header)` بررسی کنید، `throttle` بگذارید و فقط فیلدهای لازم را لاگ کنید نه `$request->all()`.

### ✅ 🟠 ۴.۲ — همگام‌سازی مخزن Git از طریق webhook بدون اعتبارسنجی URL — **حل‌شده**

`backend/Modules/Integrations/Http/Controllers/WebinocrmGitWebhookController.php:59-73`

```php
$data = $request->validate([
    'module_slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
    'repo_url' => ['required', 'string', 'max:2048'],     // ← بدون url و بدون محدودیت دامنه
]);
ModuleGitSource::query()->updateOrCreate(['slug' => $data['module_slug']], ['clone_url' => $data['repo_url'], ...]);
PropagateModuleGitRepositoryJob::dispatch($data['module_slug']);
```

`module_slug` به‌درستی محدود شده، اما `repo_url` هر رشته‌ای می‌تواند باشد و بعداً برای `git clone` استفاده می‌شود. مقادیری مثل `--upload-pack=...` یا `ext::sh -c ...` در برخی نسخه‌های git منجر به اجرای دستور می‌شوند؛ حداقل یک SSRF به شبکه داخلی است. نیازمند دانستن `git_webhook_secret` است که شدت را از critical پایین می‌آورد.

**راه‌حل:** `['required','url','starts_with:https://']` به‌علاوه allowlist دامنه (github.com، مخزن داخلی) و `--` قبل از آرگومان URL در فرمان clone.

### ✅ 🟡 ۴.۳ — مسیرهای عمومی بازاریابی بدون محدودیت نرخ — **حل‌شده**

`backend/Modules/Marketing/Routes/public.php`

۲۱ مسیر `GET` عمومی که همه به دیتابیس می‌زنند، به‌علاوه `POST /consultations` (خط ۳۷) که رکورد می‌سازد. هیچ `throttle` روی گروه نیست. `POST /consultations` یعنی امکان اسپم نامحدود جدول مشاوره‌ها.

**راه‌حل:** `Route::middleware('throttle:30,1')` روی خواندنی‌ها و `throttle:5,1` روی `consultations` به‌علاوه honeypot/captcha (الگویی که `FormController.php:89` و `ElementorLeadController.php:52` با `recaptcha_token` و `website` دارند).

### ✅ 🟡 ۴.۴ — نبود کش روی مسیرهای عمومی پرترافیک — **حل‌شده**

همان فایل: هر بازدید صفحه اول سایت عمومی چند کوئری می‌زند و `frontend/src/app/(site)/layout.tsx` هم `export const dynamic = 'force-dynamic'` دارد، پس هیچ لایه کشی وجود ندارد. این تصمیم برای رفع مشکل نصب اولیه گرفته شد ولی برای production باید با `revalidate` جایگزین شود.

---

## ۵) راست‌چین/چپ‌چین (RTL/LTR) و بین‌المللی‌سازی

زنجیره فعلی هلپرها در واقع **متمرکز و درست** طراحی شده است:

```
packages/webina-ui  →  isRtlLocale(), normalizeUiLocale(), toLocaleDigits()
        ↓
src/lib/locale/index.ts  →  htmlDir(locale): "rtl" | "ltr"
        ↓
src/hooks/use-locale-next.ts  →  { locale, isRtl }
        ↓
src/hooks/use-text-direction.ts  →  useTextDirection(): "rtl" | "ltr"
```

مشکلات مربوط به **دور زدن** این زنجیره است، نه خودش.

### ✅ 🟠 ۵.۱ — چهار نویسنده مستقل روی `document.documentElement.dir` — **حل‌شده**

| فایل | خط | نوع |
|---|---|---|
| `src/app/layout.tsx` | 41 | سرور — مقدار `<html dir>` را در HTML اولیه می‌گذارد |
| `src/providers/AppProviders.tsx` | 92 | کلاینت — `useEffect` |
| `src/hooks/useLocaleSync.ts` | 16 | کلاینت — `useEffect` |
| `src/components/LanguageMenu.tsx` | 31 | کلاینت — هندلر کلیک |

`layout.tsx` مقدار را از `getLocale()` (کوکی `NEXT_LOCALE`) می‌گیرد، ولی `AppProviders.tsx:63` مقدار را از `localStorage.getItem("locale")` می‌خواند. این دو منبع می‌توانند واگرا شوند (مثلاً کاربر کوکی را پاک کند یا در تب دیگری زبان را عوض کند) و نتیجه‌اش **عدم تطابق hydration** و پرش چیدمان صفحه در اولین رندر است. همچنین دو `useEffect` مستقل روی یک attribute مسابقه می‌گذارند و ترتیب اجرایشان تضمین‌شده نیست.

**راه‌حل:** کوکی `NEXT_LOCALE` را تنها منبع حقیقت کنید. `localStorage` را فقط آینه‌ی کوکی نگه دارید نه منبع. دو `useEffect` تکراری را در یک هوک واحد (`useLocaleSync`) ادغام کنید و `AppProviders` را از این کار خارج کنید.

### ✅ 🟡 ۵.۲ — محاسبه درون‌خطی جهت، خارج از هلپر مشترک — **حل‌شده**

`src/components/ThemeMenu.tsx:31` و `src/components/AccentMenu.tsx:30`

```tsx
const dir = locale === 'fa' ? 'rtl' : 'ltr'
```

هلپر `htmlDir(locale)` دقیقاً برای همین وجود دارد. با این کد، افزودن هر زبان RTL دیگر (عربی، عبری) این دو منو را چپ‌چین می‌کند بدون هیچ خطای بیلد.

**راه‌حل:** جایگزینی با `const dir = htmlDir(locale)` یا بهتر، `useTextDirection()`.

### ✅ 🟡 ۵.۳ — سه مسیر import برای یک تابع `isRtlLocale` — **حل‌شده**

`src/lib/locale/index.ts:23-24`

```ts
export {
  isRtlLocale,
  isRtlLocale as isRtlLocaleShared,   // ← نام دوم برای همان تابع
```

و مجدداً در `src/lib/locale/format-date.ts:43-45`:

```ts
export function isRtlLocale(locale: Locale): boolean {
  return isRtlLocaleShared(locale);   // ← لایه سوم
}
```

سه راه import برای یک منطق. باعث می‌شود جست‌وجوی «کجا جهت تعیین می‌شود» نتایج ناقص بدهد و رفع اشکال آینده سخت شود.

**راه‌حل:** فقط `isRtlLocale` را از `@webina/ui` صادر کنید؛ نام مستعار `isRtlLocaleShared` و بازپیچش در `format-date.ts` را حذف کنید.

### ✅ 🟡 ۵.۴ — کلاس‌های فیزیکی CSS در ۲۵ فایل — **حل‌شده**

اندازه‌گیری کامل روی `src/`:

```
کلاس‌های فیزیکی (شکننده در RTL):  ۸۵ مورد در ۲۵ فایل
کلاس‌های منطقی (سالم در RTL):    ۲۸۷ مورد در ۶۵ فایل
```

نسبت کلی خوب است (۷۷٪ منطقی)، ولی تمرکز مشکل در پایه‌ای‌ترین کامپوننت‌ها است:

| فایل | تعداد |
|---|---|
| `src/components/ui/sidebar.tsx` | 23 |
| `src/components/ui/dropdown-menu.tsx` | 9 |
| `src/components/ui/select.tsx` | 5 |
| `src/components/ui/dialog.tsx` | 4 |
| `src/components/ui/sheet.tsx` | 4 |
| `src/components/dashboard/ResourceListCard.tsx` | 3 |
| `src/components/dashboard/pages/accounting/AccInvoices.tsx` | 3 |
| `src/components/ui/{alert-dialog,popover,calendar}.tsx` | 2 هرکدام |
| ۹ صفحه حسابداری دیگر | 2 هرکدام |

`sidebar.tsx` با ۲۳ مورد بحرانی‌ترین است چون در هر صفحه داشبورد رندر می‌شود — سایدبار در حالت فارسی از سمت اشتباه باز می‌شود یا offset اشتباه دارد.

**راه‌حل:** جایگزینی مکانیکی `ml-→ms-`، `mr-→me-`، `pl-→ps-`، `pr-→pe-`، `left-→start-`، `right-→end-`، `text-left→text-start`، `text-right→text-end`، `border-l→border-s`، `border-r→border-e`، `space-x-→gap-x-`. برای `translate-x-` باید با `rtl:` variant یا `rtl:-translate-x-*` جفت شود. سپس یک قاعده ESLint (`no-restricted-syntax` روی رشته className) بگذارید تا برنگردند.

### ✅ 🔵 ۵.۵ — هلپری که مقدار فیزیکی برمی‌گرداند — **حل‌شده**

`src/lib/locale/index.ts:40`

```ts
return isRtlLocale(locale) ? "right" : "left"
```

این هلپر خودش مقدار فیزیکی (`left`/`right`) تولید می‌کند و مصرف‌کننده را مجبور به کلاس فیزیکی می‌کند — مخالف هدف بخش ۵.۴.

**راه‌حل:** به `"start"`/`"end"` تغییر دهید، یا اگر برای prop یک کتابخانه بیرونی لازم است، نامش را به `physicalAlign()` تغییر دهید تا قصد روشن باشد.

### ✅ 🔵 ۵.۶ — ۱۸۱۹ رشته فارسی هاردکد در ۶۸ کامپوننت — **حل‌شده**

سیستم `next-intl` راه‌اندازی شده (`src/i18n/request.ts`) اما بخش بزرگی از داشبورد آن را استفاده نمی‌کند:

| فایل | تعداد رشته |
|---|---|
| `src/components/dashboard/pages/CoreStaticPages.tsx` | 100 |
| `src/components/dashboard/pages/ContractsListPage.tsx` | 95 |
| `src/components/dashboard/pages/accounting/AccInvoices.tsx` | 94 |
| `src/components/dashboard/pages/accounting/AccChecks.tsx` | 83 |
| `src/components/dashboard/pages/accounting/AccWarehouseOutbound.tsx` | 81 |
| `src/components/dashboard/pages/accounting/AccReceipts.tsx` | 78 |
| ... ۶۲ فایل دیگر | 1288 |

یعنی سوئیچ زبان به `en` عملاً کار نمی‌کند و داشبورد فارسی می‌ماند. این یک نقص عملکردی است نه فقط سلیقه‌ای، چون `LanguageMenu` به کاربر وعده تغییر زبان می‌دهد.

**راه‌حل:** به‌ترتیب اولویت (حسابداری اول، چون بیشترین حجم را دارد) رشته‌ها را به `messages/fa.json` منتقل و با `useTranslations()` جایگزین کنید.

---

## ۶) تاریخ جلالی و دیت‌پیکر

این بخش شامل **جدی‌ترین باگ صحت داده** در کل پروژه است.

### ✅ 🔴 ۶.۱ — دیت‌پیکر جلالی، تاریخ جلالی را به‌عنوان ISO میلادی به API می‌فرستد — **حل‌شده**

`frontend/src/components/ui/date-picker-jalali.tsx:30-48`

```tsx
<DatePicker
  calendar={persian}          // ← DateObject روی تقویم جلالی است
  locale={persian_fa}
  onChange={(d: unknown) => {
    const obj = d as { format?: (f: string) => string };
    if (typeof obj.format === 'function') {
      onChange(obj.format('YYYY-MM-DD'));   // ← جلالی برمی‌گرداند، نه میلادی
    }
```

چون `DateObject` با `calendar={persian}` ساخته شده، `format('YYYY-MM-DD')` مقدار **جلالی** برمی‌گرداند. یعنی انتخاب امروز (۱۲ شهریور ۱۴۰۵) رشته `"1405-06-12"` تولید می‌کند، نه `"2026-09-03"`.

این در تضاد کامل با مستندات خود فایل و مصرف‌کننده‌اش است:

```
date-picker-jalali.tsx:18   /** Controlled Jalali date picker; value/onChange use ISO `yyyy-mm-dd` strings. */
locale-date-picker.tsx:6    * **Always stores ISO Gregorian strings** (`YYYY-MM-DD`) via `onChange`.
```

تأیید شد که هیچ import از تقویم `gregorian` در فایل وجود ندارد (`grep -c gregorian` → `0`)، پس تبدیلی انجام نمی‌شود.

**دامنه اثر — ۸ صفحه مصرف‌کننده، عمدتاً مالی:**

```
src/components/dashboard/pages/accounting/AccChecks.tsx      ← تاریخ چک
src/components/dashboard/pages/accounting/AccInvoices.tsx    ← تاریخ فاکتور
src/components/dashboard/pages/accounting/AccLedger.tsx      ← دفتر کل
src/components/dashboard/pages/accounting/AccReceipts.tsx    ← رسیدها
src/components/dashboard/pages/accounting/AccReports.tsx     ← بازه گزارش
src/components/dashboard/pages/AppointmentsListPage.tsx      ← قرار ملاقات
src/components/dashboard/pages/CoreStaticPages.tsx
src/features/modules/core/core_pages.tsx
```

Laravel رشته `1405-06-12` را به‌عنوان سال ۱۴۰۵ **میلادی** تفسیر می‌کند. نتیجه: یا خطای اعتبارسنجی، یا ثبت سکوت‌آمیز تاریخ سرخط ۶۲۰ سال در آینده در جدول‌های مالی. تاریخ سرخط چک و فاکتور، پایه محاسبات مالی است.

**راه‌حل:**

```tsx
import gregorian from 'react-date-object/calendars/gregorian';
// ...
onChange(new DateObject(d).convert(gregorian).format('YYYY-MM-DD'));
```

### ✅ 🔴 ۶.۲ — سمت خواندن هم تقویم را مشخص نمی‌کند، پس رفت‌وبرگشت شکسته است — **حل‌شده**

`frontend/src/components/ui/date-picker-jalali.tsx:24`

```tsx
const d = new DateObject({ date: value, format: 'YYYY-MM-DD' });   // ← بدون calendar
```

`DateObject` بدون `calendar` به میلادی پیش‌فرض می‌افتد، در حالی که `<DatePicker calendar={persian}>` انتظار جلالی دارد. ترکیب با ۶.۱ یعنی چرخه کامل شکسته است:

```
کاربر ۱۲ شهریور ۱۴۰۵ را انتخاب می‌کند
  → onChange مقدار "1405-06-12" را می‌دهد          (۶.۱)
  → در API/state ذخیره می‌شود
  → دوباره به‌عنوان value خوانده می‌شود
  → به‌عنوان ۱۲ ژوئن ۱۴۰۵ میلادی پارس می‌شود        (۶.۲)
  → در دیت‌پیکر تاریخی حدود سال ۷۸۴ جلالی نمایش داده می‌شود
```

پس ویرایش یک رکورد موجود، تاریخ را نمایش نمی‌دهد یا مقدار بی‌معنا نشان می‌دهد.

**راه‌حل:** `new DateObject({ date: value, format: 'YYYY-MM-DD', calendar: gregorian }).convert(persian)`.

### ✅ 🟠 ۶.۳ — خطای یک‌روزه منطقه زمانی در دیت‌پیکر میلادی — **حل‌شده**

`frontend/src/components/ui/locale-date-picker.tsx:76`

```tsx
onChange?.(d.toISOString().slice(0, 10));
```

`react-day-picker` یک `Date` با نیمه‌شب **محلی** می‌دهد. `toISOString()` آن را به UTC تبدیل می‌کند. برای `Asia/Tehran` (+03:30):

```
انتخاب: 2026-09-03 00:00:00 +03:30
toISOString(): "2026-09-02T20:30:00.000Z"
.slice(0,10):  "2026-09-02"     ← یک روز عقب‌تر
```

هر تاریخی که کاربر ایرانی در حالت میلادی انتخاب کند یک روز عقب ثبت می‌شود.

**راه‌حل:** از اجزای محلی تاریخ بسازید، نه UTC:

```tsx
const pad = (n: number) => String(n).padStart(2, '0');
onChange?.(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
```

### ✅ 🟠 ۶.۴ — انتخاب تقویم بر اساس `useParams().locale` که همیشه `undefined` است — **حل‌شده**

`frontend/src/components/ui/locale-date-picker.tsx:28-32`

```tsx
const params = useParams();
const locale = (params?.locale as string) || 'fa';
...
if (locale === 'fa') { return <JalaliDatePicker ... /> }
```

پروژه از مسیرهای `[locale]` استفاده نمی‌کند (تأیید شد: پوشه `src/app/[locale]` وجود ندارد؛ زبان از کوکی `NEXT_LOCALE` در `src/i18n/request.ts` می‌آید). پس `params?.locale` همیشه `undefined` است و همیشه به `'fa'` می‌افتد.

پیامد: کاربر انگلیسی‌زبان هم دیت‌پیکر جلالی می‌بیند، و شاخه `GregorianDatePicker` (خطوط ۵۵-۸۵) **کد مرده** است. نکته مثبت ناخواسته: باگ ۶.۳ فعلاً فعال نمی‌شود — اما به‌محض رفع ۶.۴ ظاهر می‌شود، پس هر دو باید همزمان حل شوند.

**راه‌حل:** `const { locale } = useLocale()` از `@/hooks/use-locale` — همان هوکی که بقیه پروژه استفاده می‌کند.

### ✅ 🟡 ۶.۵ — `usePersianDigits: true` سراسری، خروجی تاریخ را غیرقابل‌پارس می‌کند — **حل‌شده**

`frontend/src/lib/locale/format-date.ts:5`

```ts
moment.loadPersian({ dialect: 'persian-modern', usePersianDigits: true });
```

این یک تغییر **سراسری** روی نمونه `moment-jalaali` است که در هر ماژولی که آن را import کند اثر دارد. خروجی `formatDate()` ارقام فارسی (`۱۴۰۵/۰۶/۱۲`) دارد که برای نمایش درست است، اما:

- هر جایی که خروجی `formatDate` به `new Date()` یا به API برگردد، پارس می‌شکند.
- شاخه انگلیسی (خطوط ۱۹-۲۰) `m.locale('en').format('YYYY-MM-DD')` است؛ با پرچم سراسری فعال، تضمینی نیست که ارقام لاتین برگردد.

**راه‌حل:** `usePersianDigits: false` کنید و تبدیل رقم را در لایه نمایش با `toLocaleDigits()` (که همان فایل در خط ۴۷ صادر می‌کند) انجام دهید — تفکیک صریح «مقدار» از «نمایش».

### ✅ 🟡 ۶.۶ — `formatDisplayDate` به رشته جلالی تولیدشده در بک‌اند اعتماد می‌کند — **حل‌شده**

`frontend/src/lib/locale/format-date.ts:27-34`

```ts
export function formatDisplayDate(iso?, jalali?, locale: Locale = 'fa'): string {
  if (locale === 'fa' && jalali) return jalali;    // ← رشته خام بک‌اند، بدون هیچ نرمال‌سازی
```

دو منبع حقیقت برای یک تاریخ. اگر بک‌اند قالب متفاوتی بدهد (`1405/06/12` در مقابل `۱۴۰۵-۰۶-۱۲`) یا رشته کهنه باشد، UI ناهمگون می‌شود و هیچ اعتبارسنجی‌ای انجام نمی‌شود.

**راه‌حل:** فقط `iso` را منبع حقیقت بگیرید و همیشه در فرانت‌اند فرمت کنید. فیلد `jalali` را از پاسخ‌های API حذف کنید.

### ✅ 🟡 ۶.۷ — سه کتابخانه تاریخ همزمان برای یک کار — **حل‌شده**

| کتابخانه | مصرف‌کننده | نقش |
|---|---|---|
| `moment-jalaali` | `src/lib/locale/format-date.ts` | فرمت‌دهی نمایشی |
| `react-multi-date-picker` + `react-date-object` | `src/components/ui/date-picker-jalali.tsx` | انتخاب جلالی |
| `react-day-picker` | `src/components/ui/calendar.tsx` → `locale-date-picker.tsx` | انتخاب میلادی |

`moment` (که خودش deprecated است) حدود ۲۹۰KB به باندل اضافه می‌کند و منطق تبدیل تقویم در دو جای مستقل با دو API متفاوت پیاده شده — که ریشه ناسازگاری ۶.۱/۶.۲ است.

**راه‌حل:** روی `react-date-object` (که همین حالا برای پیکر لازم است) یکپارچه شوید و `format-date.ts` را روی آن بازنویسی کنید. این کار `moment-jalaali` و `moment` را کامل حذف می‌کند و باگ ۳.۸ را هم برطرف می‌کند.

### ✅ 🔵 ۶.۸ — `src/lib/jalali.ts` تکراری و بی‌استفاده — **حل‌شده**

فایل فقط `toPersianDigits()` دارد که دقیقاً کار `toLocaleDigits()` از `@webina/ui` را می‌کند (که در `format-date.ts:47` صادر شده). کامنت خودش هم می‌گوید «extend with moment-jalaali / react-multi-date-picker as needed» — یعنی یک stub رهاشده.

**راه‌حل:** فایل را حذف و مصرف‌کنندگان را به `toLocaleDigits` منتقل کنید.

---

## ۷) موارد تأییدشده به‌عنوان سالم

برای پرهیز از دوباره‌کاری، این موارد بررسی و **بدون مشکل** یافت شدند:

- **`@/` importها** — هر ۳۳۸ فایل `.ts/.tsx` اسکن شد؛ صفر import حل‌نشده.
- **مرز client/server در Next.js** — صفر فایل با هوک React بدون دستور `"use client"`.
- **توکن در `localStorage` نیست** — تنها موارد `localStorage` مربوط به `locale`، `theme_mode` و `theme_accent` است. توکن احراز هویت در کوکی `HttpOnly` است (درست).
- **رمزنگاری اسرار در Platform** — `PlatformSshKey.private_key`، `PlatformSource.token`، `PlatformStorage.access_key/secret_key` همه cast `encrypted` دارند و migration هم آن را مستند کرده.
- **`SshKeyController`** — در `index` و `store` ستون‌ها صریح انتخاب می‌شوند، پس کلید خصوصی افشا نمی‌شود.
- **`DockerRemoteService`** — همه فراخوانی‌های ورودی‌دار از `escapeshellarg` استفاده می‌کنند (`pullImage`، `deleteImage`، `containerAction`، `logs`، `createNetwork`، `composeUp/Down`، `writeFile`).
- **`WebinocrmGitWebhookController`** — تأیید امضای HMAC-SHA256 با `hash_equals` روی بدنه خام، به‌درستی پیاده شده.
- **`autoLogin`** — طراحی امن: HMAC با `APP_KEY`، `hash_equals`، انقضای ۵ دقیقه، مصرف یک‌بار با `Cache::pull`، بررسی `is_active`، و صدور توکن ۱۲ ساعته. صادرکننده هم به `system_manager` محدود است.
- **OTP با `random_int`** — همه تولیدهای OTP از `random_int` استفاده می‌کنند نه `rand`/`mt_rand`. توکن auto-login از `random_bytes(32)`.
- **`docker-compose.prod.yml`** — به‌درستی `APP_ENV=production` و `APP_DEBUG=false` دارد. مشکل فقط این است که `install.sh` آن را اعمال نمی‌کند (۳.۱).

---

## ۸) نقشه راه پیشنهادی برای رفع (اجرا شده — نگاه کنید به «وضعیت رفع»)

ترتیب زیر بر اساس «بیشترین کاهش ریسک به ازای کمترین تغییر» چیده شده. مرحله ۱ تقریباً همه‌اش تغییر پیکربندی است و در یک نشست قابل انجام است.

### مرحله ۱ — بستن راه‌های نشت فوری (پیکربندی، بدون تغییر منطق)

| # | کار | فایل |
|---|---|---|
| ۳.۱ | `APP_ENV=production` و `APP_DEBUG=false` + اعمال `-f docker-compose.prod.yml` | `install.sh`، `.env.example` |
| ۱.۱ | حذف `'code' => $code` از لاگ | `AuthParityController.php:31` |
| ۳.۳ | افزودن `trustProxies(at: '*')` | `bootstrap/app.php` |
| ۳.۴ | بلوک `header` امنیتی | `docker/caddy/Caddyfile` |
| ۳.۲ | تولید تصادفی `POSTGRES_PASSWORD`/`REDIS_PASSWORD`/`REVERB_APP_SECRET` | `install.sh` |
| ۳.۶ | `--requirepass` روی Redis | `docker-compose.yml` |
| ۱.۱۱ | `$hidden = ['private_key']` | `PlatformSshKey.php` |

### مرحله ۲ — بستن مسیرهای سوءاستفاده

| # | کار | فایل |
|---|---|---|
| ۲.۱ | محدود کردن `terminal` به `role:system_manager`، بستن `is_localhost`، حذف `fromShellCommandline` | `ServerController.php`، `SshExecutor.php`، `Routes/api.php` |
| ۱.۲ | حذف ساخت خودکار کاربر از `verifyLoginOtp` | `AuthParityController.php:79-88` |
| ۴.۱ | تأیید امضای webhook بله و تلگرام (با الگوی `WebinocrmGitWebhookController`) | `BaleIntegrationController.php`، `TelegramIntegrationController.php` |
| ۱.۷ | تفکیک محدودیت نرخ OTP بر اساس شماره موبایل | `AppServiceProvider.php` |
| ۱.۸ | `hash_equals` + شمارنده تلاش + ابطال کد | `AuthParityController.php`، `TwoFactorController.php` |
| ۱.۶ | الزام `current_password` + ابطال سایر توکن‌ها | `AuthParityController.php:108` |

### مرحله ۳ — اصلاح مدل مجوز

| # | کار | فایل |
|---|---|---|
| ۲.۲ | برعکس کردن پیش‌فرض به fail-closed | `EnforceModulePermission.php:47-63` |
| ۲.۳ | استفاده از پیشوند واقعی مسیر به‌جای بازسازی رشته | `EnforceModulePermission.php:52` |
| ۱.۳ | middleware واقعی `require.2fa` + ارسال واقعی کد | `TwoFactorController.php` + middleware جدید |
| ۱.۱۲ | middleware `EnsureUserIsActive` + ابطال توکن | `bootstrap/app.php` + observer |
| ۲.۴ / ۲.۵ | جایگزینی `$request->all()` با FormRequest | `ScmWarehouseController.php`، ماژول Accounting |

### مرحله ۴ — درستی داده تاریخ

| # | کار | فایل |
|---|---|---|
| ۶.۱ | `convert(gregorian)` قبل از `format('YYYY-MM-DD')` | `date-picker-jalali.tsx:44` |
| ۶.۲ | مشخص کردن `calendar` در `new DateObject` | `date-picker-jalali.tsx:24` |
| ۶.۳ | جایگزینی `toISOString().slice(0,10)` با فرمت محلی | `locale-date-picker.tsx:76` |
| ۶.۴ | خواندن locale از `useLocale()` به‌جای `useParams()` | `locale-date-picker.tsx:28` |

> پس از ۶.۱ و ۶.۲ حتماً داده‌های موجود را بررسی کنید: رکوردهای تاریخ‌داری که از زمان انتشار این پیکر ثبت شده‌اند احتمالاً سال ۱۴۰x دارند و نیاز به migration اصلاحی دارند.

### مرحله ۵ — سخت‌سازی و بدهی فنی

۳.۵ (CORS صریح)، ۳.۷ (`775` به‌جای `777`)، ۳.۸ (وابستگی‌های اعلام‌نشده)، ۳.۹ (نام پورت)، ۴.۲ (اعتبارسنجی URL مخزن)، ۴.۳/۴.۴ (throttle و کش مسیرهای عمومی)، ۱.۹ (حذف یا تکمیل OTP ایمیل)، ۱.۱۰ (`is_active` از `$fillable`)، ۲.۶ (سیاست کلید SSH)، و کل بخش ۵ (RTL) و ۶.۵–۶.۸.
