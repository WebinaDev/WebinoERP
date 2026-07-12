'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { usePermissions } from '@/features/shared/hooks/usePermissions';

type Props = {
  permission?: string;
  role?: string;
  children: React.ReactNode;
};

export function ModuleRouteGuard({ permission, role, children }: Props) {
  const router = useRouter();
  const { loading, can, hasRole } = usePermissions();

  useEffect(() => {
    if (loading) return;
    const denied = (permission && !can(permission)) || (role && !hasRole(role));
    if (denied) {
      router.replace('/dashboard');
    }
  }, [loading, can, hasRole, permission, role, router]);

  if (loading) return null;
  if (permission && !can(permission)) return null;
  if (role && !hasRole(role)) return null;

  return <>{children}</>;
}
