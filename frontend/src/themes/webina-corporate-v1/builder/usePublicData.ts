'use client';

import { useEffect, useState } from 'react';
import { useBuilderRuntime } from '@/builder/render/runtime';

const cache = new Map<string, Promise<unknown>>();

function base() {
  return (process.env.NEXT_PUBLIC_API_URL || '/api').replace(/\/$/, '');
}

/** Client fetch of a public API path (e.g. `/v1/public/site`), shared by every widget on the page. */
export function fetchPublic<T>(path: string): Promise<T | null> {
  if (!cache.has(path)) {
    cache.set(
      path,
      fetch(`${base()}${path}`, { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .catch(() => null),
    );
  }
  return cache.get(path) as Promise<T | null>;
}

export function usePublicData<T>(path: string | null): T | null {
  const pre = useBuilderRuntime().prefetch;
  const known = path && pre && path in pre ? (pre[path] as T) : undefined;
  const [data, setData] = useState<T | null>(known ?? null);
  useEffect(() => {
    if (!path || known !== undefined) return;
    let live = true;
    void fetchPublic<{ data?: T }>(path).then((res) => {
      if (live) setData((res?.data ?? null) as T | null);
    });
    return () => {
      live = false;
    };
  }, [path, known]);
  return known ?? data;
}
