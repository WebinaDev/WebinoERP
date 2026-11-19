import { describe, expect, it } from 'vitest';
import { resolveRoutePermission } from '@/lib/route-guards';
import { resolveDashboardRoute } from '@/lib/dashboard-routes';
import { getLoginErrorMessage } from '@/lib/api-helpers';

describe('user management wiring', () => {
  it('guards the users admin pages with core.users.view', () => {
    expect(resolveRoutePermission('admin/users')).toBe('core.users.view');
    expect(resolveRoutePermission('admin/users/12')).toBe('core.users.view');
  });

  it('resolves list and detail route meta', () => {
    expect(resolveDashboardRoute('admin/users').titleEn).toBe('Users');
    expect(resolveDashboardRoute('admin/users/7').apiHint).toBe('/v1/core/users/7');
  });

  it('maps locked and disabled login codes', () => {
    const locked = { isAxiosError: true, response: { status: 403, data: { errors: { code: 'ACCOUNT_LOCKED' }, message: 'x' } } };
    const disabled = { isAxiosError: true, response: { status: 403, data: { errors: { code: 'ACCOUNT_DISABLED' }, message: 'x' } } };
    expect(getLoginErrorMessage(locked)).not.toBe(getLoginErrorMessage(disabled));
    expect(getLoginErrorMessage(locked).length).toBeGreaterThan(0);
  });
});
