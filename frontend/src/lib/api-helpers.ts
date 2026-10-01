/**
 * Laravel JSON envelopes often use `{ data: T }`.
 */
export function unwrapData<T>(res: { data: unknown }): T {
  const body = res.data as { data?: T };
  if (body && typeof body === 'object' && 'data' in body && body.data !== undefined) {
    return body.data as T;
  }
  return body as T;
}

function errorCode(err: unknown): string | undefined {
  if (!err || typeof err !== 'object' || !('response' in err)) {
    return undefined;
  }
  const data = (err as { response?: { data?: { errors?: { code?: unknown } } } }).response?.data;
  const code = data?.errors?.code;
  return typeof code === 'string' && code !== '' ? code : undefined;
}

function responseMessage(err: unknown): string | undefined {
  if (!err || typeof err !== 'object' || !('response' in err)) {
    return undefined;
  }
  const m = (err as { response?: { data?: { message?: unknown } } }).response?.data?.message;
  if (typeof m !== 'string') {
    return undefined;
  }
  const trimmed = m.trim();
  if (trimmed === '' || /^Request failed with status code/i.test(trimmed)) {
    return undefined;
  }
  return trimmed;
}

const STATUS_MESSAGES: Record<number, string> = {
  401: 'نشست شما منقضی شده است. دوباره وارد شوید.',
  403: 'دسترسی به این بخش مجاز نیست.',
  404: 'منبع درخواستی پیدا نشد.',
  409: 'این عملیات با وضعیت فعلی در تضاد است.',
  422: 'اطلاعات ارسال‌شده معتبر نیست.',
  429: 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.',
  500: 'خطای داخلی سرور. صفحه را تازه کنید یا کمی بعد دوباره تلاش کنید.',
  502: 'سرور در دسترس نیست. کمی بعد دوباره تلاش کنید.',
  503: 'سرور موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.',
};

const KEY_MESSAGES: Record<string, string> = {
  '2FA_REQUIRED': 'احراز هویت دو مرحله‌ای لازم است. کد ارسال‌شده را وارد کنید.',
  ACCOUNT_DISABLED: 'این حساب غیرفعال است.',
  FORBIDDEN: 'دسترسی به این بخش مجاز نیست.',
  UNAUTHORIZED: 'نشست شما منقضی شده است. دوباره وارد شوید.',
  AJAX_REQUIRED: 'درخواست نامعتبر است. صفحه را تازه کنید.',
  MODULE_NOT_ACTIVE: 'این ماژول فعال نیست.',
  'auth.unauthorized': 'نشست شما منقضی شده است. دوباره وارد شوید.',
  'auth.forbidden': 'دسترسی به این بخش مجاز نیست.',
  'errors.not_found': 'منبع درخواستی پیدا نشد.',
  'errors.server': 'خطای داخلی سرور. صفحه را تازه کنید یا کمی بعد دوباره تلاش کنید.',
  'validation.failed': 'اطلاعات ارسال‌شده معتبر نیست.',
  'Two-factor authentication required': 'احراز هویت دو مرحله‌ای لازم است. کد ارسال‌شده را وارد کنید.',
  'platform.provision_hmac_missing':
    'سکرت HMAC یا توکن پروویژن تنظیم نیست. در تنظیمات هاستینگ ERP و .env سایت tenant بررسی کنید.',
  'platform.remote_tenant_api_not_supported': 'این عملیات فقط برای سایت‌های هم‌سرور (local) پشتیبانی می‌شود.',
  'platform.remote_update_not_supported': 'آپدیت از راه دور پشتیبانی نمی‌شود؛ فقط سایت هم‌سرور.',
  'platform.remote_domain_change_not_supported': 'تغییر دامنه از راه دور پشتیبانی نمی‌شود.',
  'platform.remote_ssl_renew_not_supported': 'تمدید SSL از راه دور پشتیبانی نمی‌شود.',
  'platform.remote_repair_db_not_supported': 'تعمیر دیتابیس از راه دور پشتیبانی نمی‌شود.',
  'platform.remote_caddy_missing':
    'روی سرور ریموت کانتینر Caddy روی شبکه webino_sites با مانت /var/lib/webino/caddy.d پیدا نشد.',
  'platform.caddy_snippet_write_failed': 'نوشتن اسنیپت Caddy ناموفق بود.',
  'platform.compose_up_failed': 'بالا آوردن استک Docker ناموفق بود.',
  'platform.dashboard_images_missing': 'ایمیج‌های webino-backend و webino-next روی سرور موجود نیست.',
  'platform.tenant_api_failed': 'درخواست به API سایت tenant ناموفق بود.',
  'platform.build_script_missing': 'اسکریپت ساخت ایمیج داشبورد روی سرور ERP پیدا نشد.',
  'platform.invalid_domain': 'دامنه نامعتبر است.',
  'platform.no_ready_server': 'سرور آماده‌ای برای پروویژن پیدا نشد.',
  'platform.license_revoked':
    'لایسنس این دامنه لغو یا ابطال شده است. در مدیریت لایسنس آن را فعال کنید، یا دامنهٔ دیگری انتخاب کنید.',
  'platform.license_wrong_product':
    'برای این دامنه لایسنس محصول دیگری ثبت شده است. ساخت سایت فقط با لایسنس محصول داشبورد (webinodashboard) ممکن است.',
  'platform.license_customer_conflict':
    'این دامنه برای مشتری دیگری لایسنس دارد. همان مشتری را انتخاب کنید یا دامنهٔ دیگری بگذارید.',
  'platform.license_reused': 'لایسنس موجود این دامنه دوباره استفاده شد.',
  'platform.queue_unavailable':
    'ساخت سایت در صف قرار نگرفت. worker و اتصال Redis (یا صف database) را بررسی کنید.',
  'Unable to queue site provisioning.':
    'ساخت سایت در صف قرار نگرفت. worker و اتصال Redis (یا صف database) را بررسی کنید.',
  'platform.schema_outdated':
    'ستون progress در جدول سایت‌ها نیست چون مایگریشن‌ها اعمال نشده‌اند. روی سرور php artisan migrate --force را اجرا کنید و دوباره «ایجاد سایت» بزنید.',
  'Site must be ready.': 'سایت باید در وضعیت آماده (ready) باشد.',
  'Provision cannot be edited in current status.': 'سایت در این وضعیت قابل ویرایش نیست.',
};

/** Queue outage and the missing `progress` column must not look like form validation. */
function schemaOrQueueMessage(text: string): string | undefined {
  const trimmed = text.trim()
  if (KEY_MESSAGES[trimmed]) {
    return KEY_MESSAGES[trimmed]
  }
  const lower = trimmed.toLowerCase()
  if (lower.includes('unable to queue site provisioning')) {
    return KEY_MESSAGES['Unable to queue site provisioning.']
  }
  if (
    lower.includes('platform.schema_outdated')
    || (
      lower.includes('progress')
      && (lower.includes('webino_site_provisions') || lower.includes('column') || lower.includes('sqlstate'))
    )
  ) {
    return KEY_MESSAGES['platform.schema_outdated']
  }
  return undefined
}

function mapPlatformKey(message: string): string | undefined {
  const key = message.split(/[:\s]/)[0] ?? '';
  if (key && KEY_MESSAGES[key]) {
    return KEY_MESSAGES[key];
  }
  if (message.startsWith('platform.')) {
    return message;
  }
  return undefined;
}

function firstValidationError(err: unknown): string | undefined {
  if (!err || typeof err !== 'object' || !('response' in err)) {
    return undefined;
  }
  const errors = (err as { response?: { data?: { errors?: unknown } } }).response?.data?.errors;
  if (!errors || typeof errors !== 'object') {
    return undefined;
  }
  for (const [key, value] of Object.entries(errors as Record<string, unknown>)) {
    if (key === 'code') continue;
    if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim()) {
      return value[0];
    }
    if (typeof value === 'string' && value.trim() && value !== 'MODULE_NOT_ACTIVE') {
      return value;
    }
  }
  return undefined;
}

export function formatProvisionError(raw: string): string {
  const text = raw.trim();
  if (KEY_MESSAGES[text]) {
    return KEY_MESSAGES[text];
  }
  const schemaOrQueue = schemaOrQueueMessage(text);
  if (schemaOrQueue) {
    return schemaOrQueue;
  }
  if (text.startsWith('platform.license_')) {
    const key = text.split(/[:\s]/)[0] ?? '';
    if (key && KEY_MESSAGES[key]) {
      return KEY_MESSAGES[key];
    }
  }
  if (
    text.includes('platform.dashboard_images_missing')
    || text.includes('/opt/WebinoDashboard/docker')
    || text.includes('unable to evaluate symlinks in Dockerfile path')
  ) {
    return [
      'ایمیج‌های سایت از GitHub ساخته نشدند.',
      'ERP باید بتواند https://github.com/Webinadev/WebinoDashboard را کلون کند و webino-backend / webino-next را بسازد.',
      text.slice(0, 400),
    ].join('\n');
  }
  return text;
}

function axiosCode(err: unknown): string | undefined {
  if (!err || typeof err !== 'object' || !('code' in err)) {
    return undefined;
  }
  const code = (err as { code?: unknown }).code;
  return typeof code === 'string' && code !== '' ? code : undefined;
}

/** Browser never received an HTTP response (reset, DNS, CORS, mixed content, or client timeout). */
export function isAxiosTransportError(err: unknown): boolean {
  const code = axiosCode(err);
  if (code === 'ERR_NETWORK' || code === 'ECONNABORTED') {
    return true;
  }
  if (err instanceof Error) {
    if (err.message === 'Network Error') return true;
    if (/timeout of \d+ms exceeded/i.test(err.message)) return true;
  }
  return false;
}

export function getAxiosMessage(err: unknown): string {
  const code = errorCode(err);
  if (code && code !== 'validation.failed' && KEY_MESSAGES[code]) {
    return KEY_MESSAGES[code];
  }

  const validation = firstValidationError(err);
  if (validation && validation !== 'Server Error') {
    const schemaOrQueue = schemaOrQueueMessage(validation);
    if (schemaOrQueue) {
      return schemaOrQueue;
    }
    const mappedValidation = KEY_MESSAGES[validation] ?? mapPlatformKey(validation);
    return mappedValidation ?? validation;
  }

  const message = responseMessage(err);
  if (message) {
    if (KEY_MESSAGES[message]) {
      return KEY_MESSAGES[message];
    }
    if (message === 'Server Error') {
      return STATUS_MESSAGES[500];
    }
    if (message.includes('platform.dashboard_images_missing') || message.includes('/opt/WebinoDashboard/docker')) {
      return 'ایمیج‌های سایت از GitHub ساخته نشدند. دسترسی خروجی به github.com/Webinadev/WebinoDashboard را بررسی کنید و دوباره «ایجاد سایت» بزنید.';
    }
    if (message.startsWith('platform.tenant_api_failed')) {
      return (
        KEY_MESSAGES['platform.tenant_api_failed']
        + ' '
        + message.replace(/^platform\.tenant_api_failed:\s*/i, '').slice(0, 240)
      );
    }
    const mapped = mapPlatformKey(message);
    if (mapped && mapped !== message) {
      return mapped;
    }
    const schemaOrQueue = schemaOrQueueMessage(message);
    if (schemaOrQueue) {
      return schemaOrQueue;
    }
    // Prefer any real server message over generic HTTP status text (e.g. bare 422).
    return message;
  }

  const status =
    err && typeof err === 'object' && 'response' in err
      ? (err as { response?: { status?: number } }).response?.status
      : undefined;
  if (typeof status === 'number' && STATUS_MESSAGES[status]) {
    return STATUS_MESSAGES[status];
  }

  if (isAxiosTransportError(err)) {
    const timedOut =
      axiosCode(err) === 'ECONNABORTED'
      || (err instanceof Error && /timeout of \d+ms exceeded/i.test(err.message));
    if (timedOut) {
      return 'زمان درخواست تمام شد. اگر ساخت سایت را زده‌اید صفحه را تازه کنید — ممکن است کار در صف باشد و این پیام به‌خاطر قطع‌شدن پاسخ باشد.';
    }
    return 'اتصال به سرور برقرار نشد. درخواست به API همین سایت نرسید (باید /api روی همین دامنه باشد، نه localhost). اگر ساخت سایت را زده‌اید یک‌بار وضعیت را تازه کنید.';
  }

  if (err instanceof Error) {
    if (/^Request failed with status code (\d+)/i.test(err.message)) {
      const n = Number(RegExp.$1);
      return STATUS_MESSAGES[n] ?? 'درخواست ناموفق بود.';
    }
    if (!/^Request failed/i.test(err.message)) {
      return err.message;
    }
  }

  return 'خطای ناشناخته';
}
