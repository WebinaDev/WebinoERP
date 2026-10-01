import { describe, expect, it } from 'vitest';
import { formatProvisionError, getAxiosMessage, isAxiosTransportError } from './api-helpers';

function axiosErr(message: string, extra: Record<string, unknown> = {}) {
  const err = new Error(message);
  Object.assign(err, extra);
  return err;
}

describe('getAxiosMessage', () => {
  it('maps license conflict codes to Persian instead of generic validation', () => {
    const err = axiosErr('Request failed with status code 422', {
      response: {
        status: 422,
        data: {
          message: 'platform.license_revoked',
          errors: { code: 'platform.license_revoked' },
        },
      },
    });
    expect(getAxiosMessage(err)).toContain('لغو');
    expect(getAxiosMessage(err)).not.toBe('اطلاعات ارسال‌شده معتبر نیست.');
  });

  it('does not hide a field error behind validation.failed', () => {
    const err = axiosErr('Request failed with status code 422', {
      response: {
        status: 422,
        data: {
          message: 'validation.failed',
          errors: { domain: ['Domain already licensed'] },
        },
      },
    });
    expect(getAxiosMessage(err)).toBe('Domain already licensed');
  });

  it('treats Axios Network Error and timeouts as transport failures', () => {
    const network = axiosErr('Network Error', { code: 'ERR_NETWORK' });
    expect(isAxiosTransportError(network)).toBe(true);
    expect(getAxiosMessage(network)).toContain('/api');
    expect(getAxiosMessage(network)).not.toBe('اطلاعات ارسال‌شده معتبر نیست.');

    const timeout = axiosErr('timeout of 15000ms exceeded', { code: 'ECONNABORTED' });
    expect(isAxiosTransportError(timeout)).toBe(true);
    expect(getAxiosMessage(timeout)).toContain('صف');
  });

  it('translates license conflict codes stored on the provision error log', () => {
    expect(formatProvisionError('platform.license_customer_conflict')).toContain('مشتری');
  });
});
