'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import apiClient from '@/lib/api-client';

export type Row = Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

export type HubSummary = {
  header: Row;
  kpis: Row;
  health: Row | null;
  alerts: Row[];
  sections: string[];
  actions: Record<string, boolean>;
  identity?: Row;
  licenses_preview?: Row[];
  notes?: Row;
};

export type PageMeta = { current_page: number; last_page: number; per_page: number; total: number };

export const hubBase = (id: string) => `/v1/crm/customers/${id}/hub`;

export function useHubSummary(id: string) {
  const [data, setData] = useState<HubSummary | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [loading, setLoading] = useState(true);
  const reload = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(hubBase(id));
      setData((res.data as { data: HubSummary }).data);
      setError(null);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
  }, [id]);
  useEffect(() => {
    void reload();
  }, [reload]);
  return { data, error, loading, reload };
}

/** Loads a non-paginated hub section (identity, assets, billing, …). */
export function useHubSection<T = Row>(id: string, section: string, enabled = true, version = 0) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const reload = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    try {
      const res = await apiClient.get(`${hubBase(id)}/${section}`);
      setData((res.data as { data: T }).data);
      setError(null);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
  }, [id, section, enabled]);
  useEffect(() => {
    void reload();
  }, [reload, version]);
  return { data, loading, error, reload };
}

/** Loads a paginated hub list (invoices, payments, tickets, timeline, …). */
export function useHubList(id: string, section: string, filters: Record<string, string | undefined> = {}, enabled = true, version = 0) {
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const key = JSON.stringify(filters);
  useEffect(() => setPage(1), [key]);
  const reload = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, per_page: 15 };
      Object.entries(JSON.parse(key) as Record<string, string | undefined>).forEach(([k, v]) => {
        if (v) params[k] = v;
      });
      const res = await apiClient.get(`${hubBase(id)}/${section}`, { params });
      const body = res.data as { data: Row[]; meta: PageMeta };
      setRows(body.data ?? []);
      setMeta(body.meta ?? null);
    } catch {
      setRows([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [id, section, key, page, enabled]);
  useEffect(() => {
    void reload();
  }, [reload, version]);
  return { rows, meta, page, setPage, loading, reload };
}

/** Fetch a short-lived signed URL for a private document and open it. */
export async function openDocument(id: string, docId: string | number): Promise<void> {
  const res = await apiClient.post(`${hubBase(id)}/documents/${docId}/download-link`);
  const url = (res.data as { data: { url: string } }).data.url;
  window.open(url, '_blank', 'noopener');
}

/** Download the CSV export (auth header goes through apiClient, so fetch as a blob). */
export async function downloadHubCsv(id: string): Promise<void> {
  const res = await apiClient.get(`${hubBase(id)}/export.csv`, { responseType: 'blob' });
  const blob = res.data instanceof Blob ? res.data : new Blob([res.data as BlobPart], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `customer-${id}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

export const LIVE_POLL_MS = 30_000;

/**
 * Live hub updates: subscribes to the existing Reverb socket (private-crm.customer.{id}) when it is configured,
 * and always polls every 30s while the tab is visible as a fallback. Calls onChange on every signal.
 */
export function useHubLive(id: string, onChange: () => void, pollMs = LIVE_POLL_MS) {
  const cb = useRef(onChange);
  cb.current = onChange;
  const [connected, setConnected] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return undefined;
    const tick = () => {
      if (document.visibilityState === 'visible') cb.current();
    };
    const timer = window.setInterval(tick, pollMs);
    const onVis = () => {
      if (document.visibilityState === 'visible') cb.current();
    };
    document.addEventListener('visibilitychange', onVis);
    return () => {
      window.clearInterval(timer);
      document.removeEventListener('visibilitychange', onVis);
    };
  }, [id, pollMs]);

  useEffect(() => {
    const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
    if (typeof window === 'undefined' || !key) return undefined;
    let leave: (() => void) | null = null;
    let cancelled = false;
    void (async () => {
      try {
        const [{ default: Echo }, { default: Pusher }, { getReverbConfig }] = await Promise.all([
          import('laravel-echo'), import('pusher-js'), import('@/lib/api/chat'),
        ]);
        if (cancelled) return;
        const rv = getReverbConfig();
        (window as unknown as { Pusher: unknown }).Pusher = Pusher;
        const authEndpoint = `${rv.apiUrl.replace(/\/$/, '')}/broadcasting/auth`;
        const echo = new Echo<'pusher'>({
          broadcaster: 'pusher', key: rv.key, cluster: 'mt1', wsHost: rv.wsHost,
          wsPort: rv.wsScheme === 'https' ? 443 : rv.wsPort, wssPort: rv.wsPort, forceTLS: rv.wsScheme === 'https',
          disableStats: true, enabledTransports: rv.wsScheme === 'https' ? ['wss'] : ['ws'], authEndpoint,
          authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, done: (e: Error | null, d: unknown) => void) => {
              fetch(authEndpoint, { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }) })
                .then(async (r) => { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
                .then((d) => done(null, d))
                .catch((e: Error) => done(e, null));
            },
          }),
        });
        echo.private(`crm.customer.${id}`).listen('.CustomerHubUpdated', () => cb.current());
        setConnected(true);
        leave = () => { echo.leave(`private-crm.customer.${id}`); echo.disconnect(); };
      } catch {
        setConnected(false);
      }
    })();
    return () => { cancelled = true; leave?.(); setConnected(false); };
  }, [id]);

  return { connected };
}
