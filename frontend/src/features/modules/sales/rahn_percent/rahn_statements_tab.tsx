'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { Calculator, FilePlus2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { useLocale } from '@/hooks/use-locale-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { RAHN_API, type RahnBill, type RahnContract, type RahnSettings } from './types';
import { unwrapRahnList } from './unwrap';

type Props = { settings: RahnSettings };

function currentMonthIsoDay(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-01`;
}

function toYearMonth(isoDay: string): string {
  const m = /^(\d{4}-\d{2})/.exec(isoDay);
  return m?.[1] ?? isoDay.slice(0, 7);
}

function normalizeMonthDay(isoOrYm: string): string {
  if (/^\d{4}-\d{2}-\d{2}/.test(isoOrYm)) return isoOrYm.slice(0, 10);
  if (/^\d{4}-\d{2}$/.test(isoOrYm)) return `${isoOrYm}-01`;
  return currentMonthIsoDay();
}

export function RahnStatementsTab({ settings }: Props) {
  const t = useTranslations('sales.rahn');
  const { formatNumber, formatDate } = useLocale();
  const [contracts, setContracts] = useState<RahnContract[]>([]);
  const [contractId, setContractId] = useState('');
  /** ISO day for LocaleDatePicker; API gets YYYY-MM via toYearMonth. */
  const [monthDay, setMonthDay] = useState(currentMonthIsoDay);
  const [G, setG] = useState(0);
  const [R, setR] = useState(0);
  const [D, setD] = useState(0);
  const [X, setX] = useState(0);
  const [bill, setBill] = useState<RahnBill | null>(null);
  const [reviewAlert, setReviewAlert] = useState<string | null>(null);
  const [history, setHistory] = useState<Record<string, unknown>[]>([]);

  const yearMonth = toYearMonth(monthDay);

  useEffect(() => {
    void apiClient
      .get(`${RAHN_API}/contracts`)
      .then((res) => {
        setContracts(unwrapRahnList<RahnContract>(res.data, 'contracts'));
      })
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!contractId) {
      setHistory([]);
      return;
    }
    void apiClient
      .get(`${RAHN_API}/statements`, { params: { contract_id: Number(contractId) } })
      .then((res) => {
        setHistory(unwrapRahnList<Record<string, unknown>>(res.data, 'statements'));
      })
      .catch(() => undefined);
  }, [contractId]);

  const selected = contracts.find((c) => String(c.id) === contractId);

  const calc = async () => {
    if (!contractId) {
      toast.error(t('pickContract'));
      return;
    }
    try {
      const res = await apiClient.post(`${RAHN_API}/statements/calculate`, {
        contract_id: Number(contractId),
        year_month: yearMonth,
        G,
        R,
        D,
        X,
      });
      const data = res.data as {
        bill?: RahnBill;
        review_alert?: { message: string } | null;
        data?: { bill?: RahnBill; review_alert?: { message: string } | null };
      };
      const payload = data.bill ? data : data.data;
      if (payload?.bill) setBill(payload.bill);
      setReviewAlert(payload?.review_alert?.message ?? null);
    } catch {
      toast.error(t('calcError'));
    }
  };

  const save = async (createInvoice: boolean) => {
    if (!contractId) return;
    try {
      const res = await apiClient.post(`${RAHN_API}/statements`, {
        contract_id: Number(contractId),
        year_month: yearMonth,
        G,
        R,
        D,
        X,
        create_invoice: createInvoice,
      });
      const data = res.data as {
        bill?: RahnBill;
        review_alert?: { message: string } | null;
        message?: string;
        data?: {
          bill?: RahnBill;
          review_alert?: { message: string } | null;
          message?: string;
        };
      };
      const payload = data.bill ? data : data.data;
      if (payload?.bill) setBill(payload.bill);
      setReviewAlert(payload?.review_alert?.message ?? null);
      toast.success(payload?.message || data.message || t('statementSaved'));
      const hist = await apiClient.get(`${RAHN_API}/statements`, {
        params: { contract_id: Number(contractId) },
      });
      setHistory(unwrapRahnList<Record<string, unknown>>(hist.data, 'statements'));
    } catch {
      toast.error(t('saveError'));
    }
  };

  const salesFields = (
    [
      ['G', G, setG],
      ['R', R, setR],
      ['D', D, setD],
      ['X', X, setX],
    ] as const
  ).filter(([key]) => settings.sales_definition[key]?.enabled);

  return (
    <div className="grid gap-6 text-right lg:grid-cols-2" dir="rtl">
      <Card>
        <CardHeader>
          <CardTitle className="text-base text-right">{t('monthlyBilling')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-2">
            <Label>{t('lockedContract')}</Label>
            <Select value={contractId || '__none'} onValueChange={(v) => setContractId(v === '__none' ? '' : v)}>
              <SelectTrigger>
                <SelectValue placeholder={t('pickContract')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {contracts.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.title} — {formatNumber(Math.round(c.F))} +{' '}
                    {formatNumber(Math.round(c.p_percent * 100) / 100)}%
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {selected ? (
            <Alert>
              <AlertDescription>{selected.clause}</AlertDescription>
            </Alert>
          ) : null}
          <div className="grid gap-2">
            <Label>{t('yearMonth')}</Label>
            <LocaleDatePicker
              value={monthDay}
              onChange={(iso) => setMonthDay(normalizeMonthDay(iso))}
              placeholder={t('yearMonth')}
            />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {salesFields.map(([key, val, setter]) => (
              <div key={key} className="grid gap-2">
                <Label>{settings.sales_definition[key].label}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={val}
                  onChange={(e) => setter(Number(e.target.value) || 0)}
                />
              </div>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" onClick={() => void calc()}>
              <Calculator className="h-4 w-4" />
              {t('calculate')}
            </Button>
            <Button type="button" onClick={() => void save(false)}>
              {t('saveStatement')}
            </Button>
            <Button type="button" variant="outline" onClick={() => void save(true)}>
              <FilePlus2 className="h-4 w-4" />
              {t('saveAndInvoice')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="space-y-4">
        {reviewAlert ? (
          <Alert>
            <AlertDescription>{reviewAlert}</AlertDescription>
          </Alert>
        ) : null}
        {bill ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base text-right">{t('result')}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-muted/50 p-3">
                <div className="text-xs text-muted-foreground">S_t</div>
                <div className="text-xl font-semibold tabular-nums" dir="ltr">
                  {formatNumber(Math.round(bill.S))}
                </div>
              </div>
              <div className="rounded-lg bg-primary/10 p-3">
                <div className="text-xs text-muted-foreground">V_t = F + p·S</div>
                <div className="text-xl font-semibold tabular-nums" dir="ltr">
                  {formatNumber(Math.round(bill.V))}
                </div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">F</div>
                <div className="font-medium tabular-nums" dir="ltr">
                  {formatNumber(Math.round(bill.F))}
                </div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">p·S</div>
                <div className="font-medium tabular-nums" dir="ltr">
                  {formatNumber(Math.round(bill.p_share))}
                </div>
              </div>
              {typeof bill.Pi === 'number' ? (
                <div className="rounded-lg border p-3 sm:col-span-2">
                  <div className="text-xs text-muted-foreground">Π_t</div>
                  <div className="font-medium tabular-nums" dir="ltr">
                    {formatNumber(Math.round(bill.Pi))}
                  </div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle className="text-base text-right">{t('history')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {history.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('noHistory')}</p>
            ) : (
              history.map((row) => {
                const ym = String(row.year_month ?? '');
                const label = formatDate(normalizeMonthDay(ym)) || ym;
                return (
                  <div
                    key={String(row.id)}
                    className="flex items-center justify-between gap-2 rounded-lg border p-3 text-sm"
                    dir="rtl"
                  >
                    <span>{label}</span>
                    <span className="font-medium tabular-nums" dir="ltr">
                      {formatNumber(Math.round(Number(row.V) || 0))}
                    </span>
                  </div>
                );
              })
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
