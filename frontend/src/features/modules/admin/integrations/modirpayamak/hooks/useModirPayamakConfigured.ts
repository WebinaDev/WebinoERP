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
      const fromStats = data.stats?.configured;
      setConfigured(Boolean(fromStats ?? data.configured));
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
