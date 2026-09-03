import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { accountingWpAction } from '@/lib/accounting-wp';

export type FiscalYear = {
  id: number;
  name?: string;
  title?: string;
  is_active?: number | boolean;
};

export async function accountingFiscalYears() {
  const res = await apiClient.get('/v1/accounting/fiscal-years', { params: { per_page: 100 } });
  const items = normalizeListPayload(res.data) as FiscalYear[];
  return { success: true, data: { items } };
}

export async function accountingJournalList(params?: Record<string, unknown>) {
  const res = await apiClient.get('/v1/accounting/journals', { params });
  const data = res.data as { data?: unknown[]; total?: number; last_page?: number };
  return {
    success: true,
    data: {
      items: data.data ?? [],
      total: data.total ?? (data.data?.length ?? 0),
      last_page: data.last_page ?? 1,
    },
  };
}

export { accountingWpAction };
