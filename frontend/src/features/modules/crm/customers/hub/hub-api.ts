'use client';

import { useCallback, useEffect, useState } from 'react';
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
