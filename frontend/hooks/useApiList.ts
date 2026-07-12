'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';

export function useApiList(endpoint: string) {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(endpoint);
      const raw = unwrapData<unknown>(res);
      setRows(normalizeListPayload(raw));
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [endpoint]);

  useEffect(() => {
    void load();
  }, [load]);

  return { rows, error, loading, reload: load };
}
