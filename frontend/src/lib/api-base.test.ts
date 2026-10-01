import { describe, expect, it } from 'vitest';
import { resolveApiBase } from './api-base';

describe('resolveApiBase', () => {
  it('uses same-origin /api when the baked URL is localhost on a remote page', () => {
    expect(resolveApiBase('http://localhost/api', 'https://webinaagency.ir')).toBe('/api');
    expect(resolveApiBase('http://backend:8080/api', 'https://webinaagency.ir')).toBe('/api');
    expect(resolveApiBase('http://127.0.0.1:8080', 'https://erp.example.com')).toBe('/api');
  });

  it('rewrites http API URLs on an https page (mixed content is a network error)', () => {
    expect(resolveApiBase('http://webinaagency.ir/api', 'https://webinaagency.ir')).toBe('/api');
  });

  it('keeps a relative /api and a matching localhost dev origin', () => {
    expect(resolveApiBase('/api', 'https://webinaagency.ir')).toBe('/api');
    expect(resolveApiBase('', 'https://webinaagency.ir')).toBe('/api');
    expect(resolveApiBase('http://localhost:8080/api', 'http://localhost:3000')).toBe(
      'http://localhost:8080/api',
    );
  });
});
