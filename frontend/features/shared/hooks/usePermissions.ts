'use client';

import { useCallback, useEffect, useState } from 'react';
import { getCurrentUser, type User } from '@/lib/auth';

export function usePermissions() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    void getCurrentUser().then((u) => {
      if (!cancelled) {
        setUser(u);
        setLoading(false);
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  const can = useCallback(
    (permission: string) => {
      if (!user) return false;
      if (user.dashboard_role === 'system_manager') return true;
      return user.permissions?.includes(permission) ?? false;
    },
    [user],
  );

  const hasRole = useCallback(
    (role: string) => {
      if (!user) return false;
      return user.roles?.includes(role) ?? user.dashboard_role === role;
    },
    [user],
  );

  return { user, loading, can, hasRole };
}
