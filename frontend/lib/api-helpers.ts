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
    const r = err as { response?: { data?: { message?: string } } };
    const m = r.response?.data?.message;
    if (typeof m === 'string') {
      return m;
    }
  }
  if (err instanceof Error) {
    return err.message;
  }
  return 'خطای ناشناخته';
}
