'use client';

import { useCallback, useEffect, useState } from 'react';
import { getModirPayamakDashboard } from '@/lib/api/modirpayamak';

export function useModirPayamakConfigured() {
  const [configured, setConfigured] = useState<boolean | null>(null);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getModirPayamakDashboard();
      setConfigured(Boolean((data as { configured?: boolean })?.configured ?? (data as { data?: { configured?: boolean } })?.data?.configured));
    } catch {
      setConfigured(false);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { configured, loading, reload };
}
