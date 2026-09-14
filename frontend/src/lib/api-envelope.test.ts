import { describe, expect, it } from 'vitest';
import { unwrapApiData, unwrapApiResponse } from '@webina/ui';

describe('api-envelope', () => {
  it('unwraps Webina success envelope', () => {
    expect(unwrapApiData({ success: true, data: { id: 1 } })).toEqual({ id: 1 });
  });

  it('preserves CRM ok payloads', () => {
    const payload = { ok: true, account: { balance: 12 } };
    expect(unwrapApiData(payload)).toEqual(payload);
  });

  it('keeps meta on unwrapApiResponse', () => {
    const out = unwrapApiResponse({ success: true, data: [1], meta: { total: 1 } });
    expect(out.data).toEqual([1]);
    expect(out.meta).toEqual({ total: 1 });
  });
});
