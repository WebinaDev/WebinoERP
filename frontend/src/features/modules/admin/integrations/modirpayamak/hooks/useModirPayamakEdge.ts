'use client';

import { useCallback, useEffect, useState } from 'react';
import type { EdgeListResult, EdgeMeta, EdgeRow } from '@/lib/api/modirpayamak-edge';

type LoaderResult = EdgeListResult & { error?: string | null };

type Options = {
  enabled?: boolean;
  deps?: unknown[];
};

export function useModirPayamakEdge(loader: () => Promise<LoaderResult>, options: Options = {}) {
  const { enabled = true, deps = [] } = options;
  const [items, setItems] = useState<EdgeRow[]>([]);
  const [meta, setMeta] = useState<EdgeMeta>({});
  const [raw, setRaw] = useState<unknown>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!enabled) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await loader();
      setItems(res.items);
      setMeta(res.meta ?? {});
      setRaw(res.raw);
      if (res.error) setError(res.error);
    } catch (err) {
      setItems([]);
      setMeta({});
      setError(err instanceof Error ? err.message : 'Load failed');
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, loader, ...deps]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { items, meta, raw, loading, error, setError, reload };
}
