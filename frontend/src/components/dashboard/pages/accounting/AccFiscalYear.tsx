'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

type FiscalYear = {
  id: number;
  title: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
};

export default function AccFiscalYear() {
  const [rows, setRows] = useState<FiscalYear[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/accounting/fiscal-years', { params: { per_page: 100 } });
      setRows(normalizeListPayload(res.data) as unknown as FiscalYear[]);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">سال مالی</h2>
        <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
          بروزرسانی
        </Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}

      {!loading && rows.length > 0 && (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full min-w-[500px] text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start font-medium">شناسه</th>
                <th className="px-2 py-2 text-start font-medium">عنوان</th>
                <th className="px-2 py-2 text-start font-medium">تاریخ شروع</th>
                <th className="px-2 py-2 text-start font-medium">تاریخ پایان</th>
                <th className="px-2 py-2 text-start font-medium">وضعیت</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((fy) => (
                <tr key={fy.id} className="border-b border-border/60">
                  <td className="px-2 py-1.5 tabular-nums">{fy.id}</td>
                  <td className="px-2 py-1.5">{fy.title}</td>
                  <td className="px-2 py-1.5">{fy.start_date}</td>
                  <td className="px-2 py-1.5">{fy.end_date}</td>
                  <td className="px-2 py-1.5">
                    <Badge variant={fy.is_active ? 'default' : 'outline'}>
                      {fy.is_active ? 'فعال' : 'غیرفعال'}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!loading && !error && rows.length === 0 && (
        <p className="text-sm text-muted-foreground">سال مالی‌ای یافت نشد</p>
      )}
    </div>
  );
}
