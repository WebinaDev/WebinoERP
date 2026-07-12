'use client';

import { useCallback, useMemo, useState } from 'react';
import { getAxiosMessage } from '@/lib/api-helpers';

export function useCrmFeedback() {
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const dismissError = useCallback(() => setError(null), []);
  const dismissSuccess = useCallback(() => setSuccess(null), []);

  const applyAxiosError = useCallback((err: unknown, fallback?: string) => {
    setSuccess(null);
    setError(getAxiosMessage(err) || fallback || '');
  }, []);

  const layoutProps = useMemo(
    () => ({
      error,
      success,
      onDismissError: dismissError,
      onDismissSuccess: dismissSuccess,
    }),
    [error, success, dismissError, dismissSuccess],
  );

  return {
    error,
    success,
    setError,
    setSuccess,
    dismissError,
    dismissSuccess,
    applyAxiosError,
    layoutProps,
  };
}
