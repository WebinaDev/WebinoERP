'use client';

import type { ReactNode } from 'react';
import { usePermissions } from '@/features/shared/hooks/usePermissions';

type Props = {
  permission?: string;
  role?: string;
  roles?: string[];
  fallback?: ReactNode;
  children: ReactNode;
};

export function PermissionGate({ permission, role, roles, fallback = null, children }: Props) {
  const { loading, can, hasRole } = usePermissions();

  if (loading) return null;

  if (role && !hasRole(role)) return <>{fallback}</>;
  if (roles?.length && !roles.some((r) => hasRole(r))) return <>{fallback}</>;
  if (permission && !can(permission)) return <>{fallback}</>;

  return <>{children}</>;
}
