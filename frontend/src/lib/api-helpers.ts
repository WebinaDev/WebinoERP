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
};

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

export function getAxiosMessage(err: unknown): string {
  const code = errorCode(err);
  if (code && KEY_MESSAGES[code]) {
    return KEY_MESSAGES[code];
  }

  const validation = firstValidationError(err);
  if (validation && validation !== 'Server Error') {
    return validation;
  }

  const message = responseMessage(err);
  if (message && KEY_MESSAGES[message]) {
    return KEY_MESSAGES[message];
  }
  if (message === 'Server Error') {
    return STATUS_MESSAGES[500];
  }
  if (message?.includes('platform.dashboard_images_missing') || message?.includes('/opt/WebinoDashboard/docker')) {
    return 'ایمیج‌های سایت از GitHub ساخته نشدند. دسترسی خروجی به github.com/Webinadev/WebinoDashboard را بررسی کنید و دوباره «ایجاد سایت» بزنید.';
  }
  if (message && !/^[a-z0-9_.]+$/i.test(message)) {
    return message;
  }

  const status =
    err && typeof err === 'object' && 'response' in err
      ? (err as { response?: { status?: number } }).response?.status
      : undefined;
  if (typeof status === 'number' && STATUS_MESSAGES[status]) {
    return STATUS_MESSAGES[status];
  }

  if (err instanceof Error) {
    if (err.message === 'Network Error') {
      return 'اتصال به سرور برقرار نشد. آدرس API را بررسی کنید.';
    }
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
