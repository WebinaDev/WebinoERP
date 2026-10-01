'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useRouter } from '@/lib/i18n-navigation';
import { getCurrentUser } from '@/lib/auth';
import { useLocaleSync } from '@/hooks/useLocaleSync';

/** Max wait for /auth/user — then treat as logged-out so UI is not stuck on loading. */
const AUTH_BOOT_TIMEOUT_MS = 15_000;

export function DashboardAuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const t = useTranslations();
  useLocaleSync();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;

    const goLogin = () => {
      if (!cancelled) {
        router.replace('/login');
      }
    };

    const timer = window.setTimeout(() => {
      goLogin();
    }, AUTH_BOOT_TIMEOUT_MS);

    void (async () => {
      try {
        const user = await getCurrentUser();
        if (cancelled) return;
        if (!user) {
          goLogin();
          return;
        }
        setReady(true);
      } catch {
        goLogin();
      } finally {
        window.clearTimeout(timer);
      }
    })();

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [router]);

  if (!ready) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      </div>
    );
  }

  return <>{children}</>;
}
