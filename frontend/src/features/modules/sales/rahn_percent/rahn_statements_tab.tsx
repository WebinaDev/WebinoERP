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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { RAHN_API, type RahnBill, type RahnContract, type RahnSettings } from './types';

type Props = { settings: RahnSettings };

export function RahnStatementsTab({ settings }: Props) {
  const t = useTranslations('sales.rahn');
  const { formatNumber } = useLocale();
  const [contracts, setContracts] = useState<RahnContract[]>([]);
  const [contractId, setContractId] = useState('');
  const [yearMonth, setYearMonth] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });
  const [G, setG] = useState(0);
  const [R, setR] = useState(0);
  const [D, setD] = useState(0);
  const [X, setX] = useState(0);
  const [bill, setBill] = useState<RahnBill | null>(null);
  const [reviewAlert, setReviewAlert] = useState<string | null>(null);
  const [history, setHistory] = useState<Record<string, unknown>[]>([]);

  useEffect(() => {
    void apiClient
      .get(`${RAHN_API}/contracts`)
      .then((res) => {
        const data = res.data as { contracts?: RahnContract[] };
        if (data?.contracts) setContracts(data.contracts);
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
        const data = res.data as { statements?: Record<string, unknown>[] };
        if (data?.statements) setHistory(data.statements);
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
        bill: RahnBill;
        review_alert: { message: string } | null;
      };
      setBill(data.bill);
      setReviewAlert(data.review_alert?.message ?? null);
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
        bill: RahnBill;
        review_alert: { message: string } | null;
        message?: string;
      };
      setBill(data.bill);
      setReviewAlert(data.review_alert?.message ?? null);
      toast.success(data.message || t('statementSaved'));
      const hist = await apiClient.get(`${RAHN_API}/statements`, {
        params: { contract_id: Number(contractId) },
      });
      const histData = hist.data as { statements?: Record<string, unknown>[] };
      if (histData?.statements) setHistory(histData.statements);
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
    <div className="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('monthlyBilling')}</CardTitle>
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
            <Input type="month" value={yearMonth} onChange={(e) => setYearMonth(e.target.value)} />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {salesFields.map(([key, val, setter]) => (
              <div key={key} className="grid gap-2">
                <Label>{settings.sales_definition[key].label}</Label>
                <Input type="number" value={val} onChange={(e) => setter(Number(e.target.value) || 0)} />
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
              <CardTitle className="text-base">{t('result')}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-muted/50 p-3">
                <div className="text-xs text-muted-foreground">S_t</div>
                <div className="text-xl font-semibold">{formatNumber(Math.round(bill.S))}</div>
              </div>
              <div className="rounded-lg bg-primary/10 p-3">
                <div className="text-xs text-muted-foreground">V_t = F + p·S</div>
                <div className="text-xl font-semibold">{formatNumber(Math.round(bill.V))}</div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">F</div>
                <div className="font-medium">{formatNumber(Math.round(bill.F))}</div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">p·S</div>
                <div className="font-medium">{formatNumber(Math.round(bill.p_share))}</div>
              </div>
              {typeof bill.Pi === 'number' ? (
                <div className="rounded-lg border p-3 sm:col-span-2">
                  <div className="text-xs text-muted-foreground">Π_t</div>
                  <div className="font-medium">{formatNumber(Math.round(bill.Pi))}</div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('history')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {history.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('noHistory')}</p>
            ) : (
              history.map((row) => (
                <div
                  key={String(row.id)}
                  className="flex items-center justify-between gap-2 rounded-lg border p-3 text-sm"
                >
                  <span>{String(row.year_month)}</span>
                  <span className="font-medium tabular-nums">
                    {formatNumber(Math.round(Number(row.V) || 0))}
                  </span>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
