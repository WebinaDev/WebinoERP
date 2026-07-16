'use client';

import { useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';

type TrialRow = { code: string; name: string; debit: number; credit: number; balance: number };
type BSRow = { code: string; name: string; balance: number };
type BSData = { assets: BSRow[]; liabilities_equity: BSRow[] };
type PLRow = { code: string; name: string; type: string; balance: number };

type ReportType = 'trial_balance' | 'balance_sheet' | 'profit_loss';

export default function AccReports() {
  const [tab, setTab] = useState<ReportType>('trial_balance');
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [data, setData] = useState<unknown>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    setError(null);
    setData(null);
    try {
      const params: Record<string, string> = { type: tab };
      if (from) params.from = from;
      if (to) params.to = to;
      const res = await apiClient.get('/v1/accounting/reports', { params });
      setData(unwrapData<unknown>(res));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold">گزارش‌های مالی</h2>

      <Tabs
        value={tab}
        onValueChange={(v) => { setTab(v as ReportType); setData(null); }}
      >
        <TabsList>
          <TabsTrigger value="trial_balance">تراز آزمایشی</TabsTrigger>
          <TabsTrigger value="balance_sheet">ترازنامه</TabsTrigger>
          <TabsTrigger value="profit_loss">سود و زیان</TabsTrigger>
        </TabsList>

        <div className="mt-4 flex flex-wrap items-end gap-3">
          <div className="w-44">
            <label className="mb-1 block text-sm font-medium">از تاریخ</label>
            <LocaleDatePicker value={from} onChange={setFrom} />
          </div>
          <div className="w-44">
            <label className="mb-1 block text-sm font-medium">تا تاریخ</label>
            <LocaleDatePicker value={to} onChange={setTo} />
          </div>
          <Button onClick={() => void load()} disabled={loading}>
            {loading ? 'در حال بارگذاری…' : 'نمایش گزارش'}
          </Button>
        </div>

        {error && <p className="mt-4 text-sm text-destructive">{error}</p>}

        <TabsContent value="trial_balance">
          {data ? <TrialBalanceTable rows={data as TrialRow[]} /> : null}
        </TabsContent>
        <TabsContent value="balance_sheet">
          {data ? <BalanceSheetView data={data as BSData} /> : null}
        </TabsContent>
        <TabsContent value="profit_loss">
          {data ? <ProfitLossTable rows={data as PLRow[]} /> : null}
        </TabsContent>
      </Tabs>

      {!loading && !error && !data && (
        <p className="text-sm text-muted-foreground">بازه زمانی را انتخاب و گزارش را نمایش دهید</p>
      )}
    </div>
  );
}

/* ─── Trial Balance ─── */

function TrialBalanceTable({ rows }: { rows: TrialRow[] }) {
  if (!Array.isArray(rows) || rows.length === 0)
    return <p className="text-sm text-muted-foreground">داده‌ای یافت نشد</p>;

  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full min-w-[600px] text-sm">
        <thead>
          <tr className="border-b bg-muted/40">
            <th className="px-2 py-2 text-start font-medium">کد</th>
            <th className="px-2 py-2 text-start font-medium">نام</th>
            <th className="px-2 py-2 text-end font-medium">بدهکار</th>
            <th className="px-2 py-2 text-end font-medium">بستانکار</th>
            <th className="px-2 py-2 text-end font-medium">مانده</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i} className="border-b border-border/60">
              <td className="px-2 py-1.5 tabular-nums">{r.code}</td>
              <td className="px-2 py-1.5">{r.name}</td>
              <td className="px-2 py-1.5 text-end tabular-nums">{Number(r.debit).toLocaleString()}</td>
              <td className="px-2 py-1.5 text-end tabular-nums">{Number(r.credit).toLocaleString()}</td>
              <td className="px-2 py-1.5 text-end tabular-nums">{Number(r.balance).toLocaleString()}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ─── Balance Sheet ─── */

function BalanceSheetView({ data }: { data: BSData }) {
  if (!data || typeof data !== 'object')
    return <p className="text-sm text-muted-foreground">داده‌ای یافت نشد</p>;

  const assets = Array.isArray(data.assets) ? data.assets : [];
  const liabilities = Array.isArray(data.liabilities_equity) ? data.liabilities_equity : [];

  return (
    <div className="space-y-6">
      <BSSection title="دارایی‌ها" rows={assets} />
      <BSSection title="بدهی‌ها و حقوق صاحبان سهام" rows={liabilities} />
    </div>
  );
}

function BSSection({ title, rows }: { title: string; rows: BSRow[] }) {
  return (
    <div>
      <h3 className="mb-2 text-sm font-semibold">{title}</h3>
      {rows.length === 0 ? (
        <p className="text-sm text-muted-foreground">داده‌ای یافت نشد</p>
      ) : (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start font-medium">کد</th>
                <th className="px-2 py-2 text-start font-medium">نام</th>
                <th className="px-2 py-2 text-end font-medium">مانده</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={i} className="border-b border-border/60">
                  <td className="px-2 py-1.5 tabular-nums">{r.code}</td>
                  <td className="px-2 py-1.5">{r.name}</td>
                  <td className="px-2 py-1.5 text-end tabular-nums">{Number(r.balance).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

/* ─── Profit & Loss ─── */

function ProfitLossTable({ rows }: { rows: PLRow[] }) {
  if (!Array.isArray(rows) || rows.length === 0)
    return <p className="text-sm text-muted-foreground">داده‌ای یافت نشد</p>;

  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full min-w-[500px] text-sm">
        <thead>
          <tr className="border-b bg-muted/40">
            <th className="px-2 py-2 text-start font-medium">کد</th>
            <th className="px-2 py-2 text-start font-medium">نام</th>
            <th className="px-2 py-2 text-start font-medium">نوع</th>
            <th className="px-2 py-2 text-end font-medium">مانده</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i} className="border-b border-border/60">
              <td className="px-2 py-1.5 tabular-nums">{r.code}</td>
              <td className="px-2 py-1.5">{r.name}</td>
              <td className="px-2 py-1.5">{r.type}</td>
              <td className="px-2 py-1.5 text-end tabular-nums">{Number(r.balance).toLocaleString()}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
