'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { listStock, listWarehouses } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';

type Warehouse = { id: number; name: string };
type StockRow = {
  id?: number;
  warehouse?: { name?: string };
  product?: { name?: string };
  quantity: number;
  reorder_point?: number | null;
};

function parsePaginated<T>(axiosData: unknown) {
  const obj = axiosData as Record<string, unknown> | null;
  if (!obj || typeof obj !== 'object') return { data: [] as T[], page: 1, pageCount: 1 };
  let envelope = obj;
  if (obj.data && typeof obj.data === 'object' && !Array.isArray(obj.data)) {
    const inner = obj.data as Record<string, unknown>;
    if (Array.isArray(inner.data)) envelope = inner;
  }
  const list = (Array.isArray(envelope.data) ? envelope.data : normalizeListPayload(envelope)) as T[];
  const meta = (envelope.meta && typeof envelope.meta === 'object' ? envelope.meta : envelope) as Record<string, unknown>;
  return {
    data: list,
    page: Number(meta.current_page ?? 1),
    pageCount: Number(meta.last_page ?? 1),
  };
}

export function StockPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [warehouseId, setWarehouseId] = useState('all');
  const [lowOnly, setLowOnly] = useState(false);
  const [rows, setRows] = useState<StockRow[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    listWarehouses({ per_page: 200 })
      .then((res) => setWarehouses(normalizeListPayload(res as { data?: unknown }) as unknown as Warehouse[]))
      .catch(() => {});
  }, []);

  const load = useCallback(async (p = 1) => {
    setLoading(true);
    try {
      const params: Record<string, string | number | boolean> = { page: p, per_page: 50 };
      if (warehouseId !== 'all') params.warehouse_id = warehouseId;
      if (lowOnly) params.low_only = 1;
      const raw = await listStock(params);
      const paged = parsePaginated<StockRow>(raw);
      setRows(paged.data);
      setPage(paged.page);
      setPageCount(paged.pageCount);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [warehouseId, lowOnly, applyAxiosError]);

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.scm.stock')} {...layoutProps}>
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Select value={warehouseId} onValueChange={setWarehouseId}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder={t('warehouse')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('allWarehouses')}</SelectItem>
            {warehouses.map((w) => (
              <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button variant={lowOnly ? 'default' : 'outline'} size="sm" onClick={() => setLowOnly((v) => !v)}>
          {t('lowStockOnly')}
        </Button>
        <Button variant="outline" size="sm" onClick={() => void load(page)} disabled={loading}>
          {tNav('common.refresh')}
        </Button>
      </div>

      {loading ? (
        <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
      ) : (
        <div className="overflow-x-auto rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('product')}</TableHead>
                <TableHead>{t('warehouse')}</TableHead>
                <TableHead>{t('quantity')}</TableHead>
                <TableHead>{t('reorderPoint')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((row, i) => {
                const isLow = row.reorder_point != null && Number(row.quantity) <= Number(row.reorder_point);
                return (
                  <TableRow key={row.id ?? i} className={cn(isLow && 'bg-destructive/10 text-destructive')}>
                    <TableCell>{row.product?.name ?? '—'}</TableCell>
                    <TableCell>{row.warehouse?.name ?? '—'}</TableCell>
                    <TableCell className="tabular-nums">{Number(row.quantity).toLocaleString()}</TableCell>
                    <TableCell className="tabular-nums">
                      {row.reorder_point != null ? Number(row.reorder_point).toLocaleString() : '—'}
                    </TableCell>
                  </TableRow>
                );
              })}
              {rows.length === 0 && (
                <TableRow>
                  <TableCell colSpan={4} className="py-6 text-center text-muted-foreground">
                    {t('noStock')}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>
      )}

      {pageCount > 1 && (
        <div className="mt-4 flex items-center justify-center gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => void load(page - 1)}>
            {tNav('common.prev')}
          </Button>
          <span className="text-sm text-muted-foreground">
            {tNav('common.pageOf', { page, pageCount })}
          </span>
          <Button variant="outline" size="sm" disabled={page >= pageCount} onClick={() => void load(page + 1)}>
            {tNav('common.next')}
          </Button>
        </div>
      )}
    </CrmPageLayout>
  );
}
