'use client';

import { useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';

type Account = { id: number; code: string; name: string };

type LedgerLine = {
  journal_entry_id: number;
  document_no: string;
  document_date: string;
  account_name: string;
  debit: number;
  credit: number;
  line_description: string;
};

type LedgerData = {
  lines: LedgerLine[];
  totals: { debit: number; credit: number };
};

export default function AccLedger() {
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [accountId, setAccountId] = useState('');
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [data, setData] = useState<LedgerData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiClient
      .get('/v1/accounting/chart')
      .then((r) => {
        const list = unwrapData<Account[]>(r);
        setAccounts(Array.isArray(list) ? list : []);
      })
      .catch(() => {});
  }, []);

  const load = async () => {
    if (!accountId) return;
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string> = { account_id: accountId };
      if (from) params.from = from;
      if (to) params.to = to;
      const res = await apiClient.get('/v1/accounting/ledger', { params });
      setData(unwrapData<LedgerData>(res));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold">دفتر کل / معین</h2>

      <div className="flex flex-wrap items-end gap-3">
        <div className="w-56">
          <label className="mb-1 block text-sm font-medium">حساب</label>
          <Select value={accountId} onValueChange={setAccountId}>
            <SelectTrigger>
              <SelectValue placeholder="انتخاب حساب" />
            </SelectTrigger>
            <SelectContent>
              {accounts.map((a) => (
                <SelectItem key={a.id} value={String(a.id)}>
                  {a.code} — {a.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="w-44">
          <label className="mb-1 block text-sm font-medium">از تاریخ</label>
          <LocaleDatePicker value={from} onChange={setFrom} />
        </div>
        <div className="w-44">
          <label className="mb-1 block text-sm font-medium">تا تاریخ</label>
          <LocaleDatePicker value={to} onChange={setTo} />
        </div>
        <Button onClick={() => void load()} disabled={!accountId || loading}>
          {loading ? 'در حال بارگذاری…' : 'نمایش'}
        </Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      {data && (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full min-w-[800px] text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start font-medium">شماره سند</th>
                <th className="px-2 py-2 text-start font-medium">ردیف</th>
                <th className="px-2 py-2 text-start font-medium">تاریخ</th>
                <th className="px-2 py-2 text-start font-medium">حساب</th>
                <th className="px-2 py-2 text-end font-medium">بدهکار</th>
                <th className="px-2 py-2 text-end font-medium">بستانکار</th>
                <th className="px-2 py-2 text-end font-medium">مانده</th>
                <th className="px-2 py-2 text-start font-medium">شرح</th>
              </tr>
            </thead>
            <tbody>
              {data.lines.map((l, i) => (
                <tr key={i} className="border-b border-border/60">
                  <td className="px-2 py-1.5 tabular-nums">{l.document_no}</td>
                  <td className="px-2 py-1.5 tabular-nums">{l.journal_entry_id}</td>
                  <td className="px-2 py-1.5">{l.document_date}</td>
                  <td className="px-2 py-1.5">{l.account_name}</td>
                  <td className="px-2 py-1.5 text-end tabular-nums">
                    {Number(l.debit).toLocaleString()}
                  </td>
                  <td className="px-2 py-1.5 text-end tabular-nums">
                    {Number(l.credit).toLocaleString()}
                  </td>
                  <td className="px-2 py-1.5 text-end tabular-nums">
                    {Number((Number(l.debit) || 0) - (Number(l.credit) || 0)).toLocaleString()}
                  </td>
                  <td className="max-w-[200px] truncate px-2 py-1.5">{l.line_description}</td>
                </tr>
              ))}
              <tr className="bg-muted/40 font-medium">
                <td colSpan={4} className="px-2 py-2 text-start">جمع</td>
                <td className="px-2 py-2 text-end tabular-nums">
                  {Number(data.totals.debit).toLocaleString()}
                </td>
                <td className="px-2 py-2 text-end tabular-nums">
                  {Number(data.totals.credit).toLocaleString()}
                </td>
                <td className="px-2 py-2 text-end tabular-nums">
                  {Number((Number(data.totals.debit) || 0) - (Number(data.totals.credit) || 0)).toLocaleString()}
                </td>
                <td />
              </tr>
            </tbody>
          </table>
        </div>
      )}

      {!loading && !error && !data && (
        <p className="text-sm text-muted-foreground">حساب مورد نظر را انتخاب و نمایش دهید</p>
      )}
    </div>
  );
}
