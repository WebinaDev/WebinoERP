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

export function getAxiosMessage(err: unknown): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const r = err as { response?: { status?: number; data?: { message?: string } } };
    const m = r.response?.data?.message;
    if (typeof m === 'string' && m.trim() !== '' && !/^Request failed with status code/i.test(m)) {
      return m;
    }
    const status = r.response?.status;
    if (status === 500) {
      return 'خطای داخلی سرور. صفحه را تازه کنید یا کمی بعد دوباره تلاش کنید.';
    }
    if (status === 403) {
      return 'دسترسی به این بخش مجاز نیست.';
    }
    if (status === 404) {
      return 'منبع درخواستی پیدا نشد.';
    }
  }
  if (err instanceof Error) {
    if (err.message === 'Network Error') {
      return 'اتصال به سرور برقرار نشد. آدرس API را بررسی کنید.';
    }
    if (/^Request failed with status code 500/i.test(err.message)) {
      return 'خطای داخلی سرور. صفحه را تازه کنید یا کمی بعد دوباره تلاش کنید.';
    }
    return err.message;
  }
  return 'خطای ناشناخته';
}
