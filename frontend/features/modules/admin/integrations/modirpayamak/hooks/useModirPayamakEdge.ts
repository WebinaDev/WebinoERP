'use client';

import { useCallback, useEffect, useState } from 'react';
import type { EdgeListResult, EdgeRow } from '@/lib/api/modirpayamak-edge';

type LoaderResult = EdgeListResult & { error?: string | null };

export function useModirPayamakEdge(loader: () => Promise<LoaderResult>) {
  const [items, setItems] = useState<EdgeRow[]>([]);
  const [raw, setRaw] = useState<unknown>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await loader();
      setItems(res.items);
      setRaw(res.raw);
      if (res.error) setError(res.error);
    } catch (err) {
      setItems([]);
      setError(err instanceof Error ? err.message : 'Load failed');
    } finally {
      setLoading(false);
    }
  }, [loader]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { items, raw, loading, error, reload };
}
