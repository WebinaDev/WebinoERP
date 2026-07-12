import { describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { usePermissions } from '@/features/shared/hooks/usePermissions';

vi.mock('@/lib/auth', () => ({
  getCurrentUser: vi.fn().mockResolvedValue(null),
}));

describe('usePermissions', () => {
  it('returns can helper defaulting to false without user', async () => {
    const { result } = renderHook(() => usePermissions());
    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.can('crm.leads.view')).toBe(false);
  });
});
