'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { Loader2, Lock, Save, Link2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { useLocale } from '@/hooks/use-locale-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { RahnRangeSlider } from './rahn_range_slider';
import { RAHN_API, type RahnCalcResponse, type RahnCatalogItem, type RahnSettings } from './types';

type Props = { settings: RahnSettings };

function money(n: number, formatNumber: (n: number) => string) {
  return formatNumber(Math.round(n));
}

export function RahnCalculatorTab({ settings }: Props) {
  const t = useTranslations('sales.rahn');
  const { formatNumber } = useLocale();
  const activeCatalog = useMemo(() => settings.catalog.filter((c) => c.active), [settings.catalog]);

  const [selected, setSelected] = useState<string[]>(() =>
    activeCatalog.filter((c) => c.default_selected).map((c) => c.id),
  );
  const [sHat, setSHat] = useState(settings.s_hat_default);
  const [duration, setDuration] = useState(settings.T);
  const [mode, setMode] = useState<'from_p' | 'from_f'>('from_p');
  const [pPercent, setPPercent] = useState(settings.p_default * 100);
  const [fWanted, setFWanted] = useState(0);
  const [calc, setCalc] = useState<RahnCalcResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [quoteId, setQuoteId] = useState<number | null>(null);
  const [shareUrl, setShareUrl] = useState<string | null>(null);
  const [customers, setCustomers] = useState<{ id: number; display_name: string }[]>([]);
  const [customerId, setCustomerId] = useState('');
  const [title, setTitle] = useState('');

  const pMinPct = settings.p_min * 100;
  const pMaxPct = settings.p_max * 100;

  const runCalc = useCallback(async () => {
    setLoading(true);
    try {
      const body: Record<string, unknown> = {
        selected_ids: selected,
        s_hat: sHat,
        T: duration,
        mode,
      };
      if (mode === 'from_p') body.p_percent = pPercent;
      else body.F_wanted = fWanted;

      const res = await apiClient.post(`${RAHN_API}/calculate`, body);
      const data = (res.data ?? {}) as RahnCalcResponse;
      setCalc(data);
      if (data.lock) {
        setPPercent(data.lock.p_percent);
        setFWanted(data.lock.F);
      }
    } catch {
      toast.error(t('calcError'));
    } finally {
      setLoading(false);
    }
  }, [selected, sHat, duration, mode, pPercent, fWanted, t]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void runCalc();
    }, 250);
    return () => window.clearTimeout(timer);
  }, [runCalc]);

  useEffect(() => {
    void apiClient
      .get(`${RAHN_API}/customers`)
      .then((res) => {
        const data = res.data as { customers?: { id: number; display_name: string }[] };
        if (data?.customers) setCustomers(data.customers);
      })
      .catch(() => undefined);
  }, []);

  const toggleItem = (id: string, checked: boolean) => {
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)));
  };

  const billingLabel = (item: RahnCatalogItem) => {
    const base = t(`billing.${item.billing}`);
    if (item.billing === 'custom') {
      return `${base} / ${item.period_months} ${t('months')}${item.renewable ? ` · ${t('renewable')}` : ''}`;
    }
    return base;
  };

  const lock = calc?.lock;
  const internal = calc?.internal;
  const fMax = Math.max(
    (lock?.V_hat ?? settings.s_hat_default) * 1.5,
    (lock?.F_min ?? 0) * 2,
    fWanted * 1.2,
    1,
  );

  const calcBody = () => ({
    id: quoteId ?? undefined,
    title,
    customer_id: customerId ? Number(customerId) : 0,
    selected_ids: selected,
    s_hat: sHat,
    T: duration,
    mode,
    p_percent: pPercent,
    F_wanted: fWanted,
  });

  const handleSaveQuote = async () => {
    try {
      const res = await apiClient.post(`${RAHN_API}/quotes`, calcBody());
      const data = res.data as { quote: { id: number; share_url: string }; calculation: RahnCalcResponse; message?: string };
      setQuoteId(data.quote.id);
      setShareUrl(data.quote.share_url);
      setCalc(data.calculation);
      toast.success(data.message || t('saved'));
    } catch {
      toast.error(t('saveError'));
    }
  };

  const handleLock = async () => {
    try {
      let id = quoteId;
      if (!id) {
        const saved = await apiClient.post(`${RAHN_API}/quotes`, calcBody());
        const savedData = saved.data as { quote: { id: number; share_url: string } };
        id = savedData.quote.id;
        setQuoteId(id);
        setShareUrl(savedData.quote.share_url);
      }
      const res = await apiClient.post(`${RAHN_API}/quotes/${id}/lock`, calcBody());
      const data = res.data as { quote: { share_url: string }; calculation: RahnCalcResponse; message?: string };
      setCalc(data.calculation);
      setShareUrl(data.quote.share_url);
      toast.success(data.message || t('locked'));
    } catch {
      toast.error(t('lockError'));
    }
  };

  const handleContract = async () => {
    if (!quoteId) {
      toast.error(t('lockFirst'));
      return;
    }
    if (!customerId) {
      toast.error(t('customerRequired'));
      return;
    }
    try {
      const res = await apiClient.post(`${RAHN_API}/quotes/${quoteId}/contract`, {
        customer_id: Number(customerId),
        contract_title: title,
      });
      const data = res.data as { message?: string };
      toast.success(data.message || t('contractCreated'));
    } catch {
      toast.error(t('contractError'));
    }
  };

  const copyShare = async () => {
    if (!shareUrl) {
      await handleSaveQuote();
      return;
    }
    try {
      await navigator.clipboard.writeText(shareUrl);
      toast.success(t('linkCopied'));
    } catch {
      toast.message(shareUrl);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-5">
      <div className="lg:col-span-2 space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('services')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {activeCatalog.map((item) => {
              const checked = selected.includes(item.id);
              return (
                <label
                  key={item.id}
                  className="flex items-start gap-3 rounded-lg border p-3 hover:bg-muted/40 cursor-pointer"
                >
                  <Checkbox
                    checked={checked}
                    onCheckedChange={(v) => toggleItem(item.id, !!v)}
                    className="mt-0.5"
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-medium">{item.name}</span>
                      {item.category ? <Badge variant="secondary">{item.category}</Badge> : null}
                    </div>
                    <div className="text-xs text-muted-foreground mt-1">
                      {billingLabel(item)} · {money(item.amount, formatNumber)} {t('toman')}
                    </div>
                  </div>
                </label>
              );
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('sessionInputs')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4">
            <div className="grid gap-2">
              <Label>{t('sHat')}</Label>
              <Input type="number" value={sHat} onChange={(e) => setSHat(Number(e.target.value) || 0)} />
            </div>
            <div className="grid gap-2">
              <Label>{t('duration')}</Label>
              <Input
                type="number"
                min={1}
                value={duration}
                onChange={(e) => setDuration(Math.max(1, Number(e.target.value) || 1))}
              />
            </div>
            <div className="grid gap-2">
              <Label>{t('quoteTitle')}</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label>{t('customer')}</Label>
              <Select value={customerId || '__none'} onValueChange={(v) => setCustomerId(v === '__none' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder={t('customer')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">—</SelectItem>
                  {customers.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.display_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="lg:col-span-3 space-y-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="text-base">{t('negotiator')}</CardTitle>
            {loading ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : null}
          </CardHeader>
          <CardContent className="space-y-6">
            {lock ? (
              <Alert>
                <AlertDescription>
                  {t('alphaHint', {
                    sHat: money(lock.S_hat, formatNumber),
                    alpha: money(lock.alpha, formatNumber),
                  })}
                </AlertDescription>
              </Alert>
            ) : null}

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <Label>{t('percentSlider')}</Label>
                <span className="font-semibold tabular-nums">
                  {formatNumber(Math.round(pPercent * 100) / 100)}%
                </span>
              </div>
              <RahnRangeSlider
                min={pMinPct}
                max={pMaxPct}
                step={0.1}
                value={Math.min(pMaxPct, Math.max(pMinPct, pPercent))}
                onValueChange={(v) => {
                  setMode('from_p');
                  setPPercent(v);
                }}
              />
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <Label>{t('fixedSlider')}</Label>
                <span className="font-semibold tabular-nums">
                  {money(fWanted, formatNumber)} {t('toman')}
                </span>
              </div>
              <RahnRangeSlider
                min={0}
                max={Math.ceil(fMax)}
                step={100000}
                value={Math.min(fMax, Math.max(0, fWanted))}
                onValueChange={(v) => {
                  setMode('from_f');
                  setFWanted(v);
                }}
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-xl border bg-primary/5 p-4">
                <div className="text-xs text-muted-foreground">{t('fixedMonthly')}</div>
                <div className="text-2xl font-bold mt-1">{money(lock?.F ?? 0, formatNumber)}</div>
              </div>
              <div className="rounded-xl border bg-emerald-500/10 p-4">
                <div className="text-xs text-muted-foreground">{t('percentOfSales')}</div>
                <div className="text-2xl font-bold mt-1">
                  {formatNumber(Math.round((lock?.p_percent ?? 0) * 100) / 100)}%
                </div>
              </div>
              <div className="rounded-xl border p-4">
                <div className="text-xs text-muted-foreground">{t('expectedValue')}</div>
                <div className="text-xl font-semibold mt-1">{money(lock?.V_hat ?? 0, formatNumber)}</div>
              </div>
              <div className="rounded-xl border p-4">
                <div className="text-xs text-muted-foreground">{t('breakeven')}</div>
                <div className="text-xl font-semibold mt-1">
                  {lock?.S_BE == null ? t('undefined') : money(lock.S_BE, formatNumber)}
                </div>
              </div>
            </div>

            {calc?.clause ? (
              <div className="rounded-xl border border-dashed p-4 text-sm leading-7 bg-muted/30">{calc.clause}</div>
            ) : null}

            {internal ? (
              <div className="grid gap-2 sm:grid-cols-3 text-sm">
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">C</div>
                  <div className="font-medium">{money(internal.C, formatNumber)}</div>
                </div>
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">V*</div>
                  <div className="font-medium">{money(internal.V_star, formatNumber)}</div>
                </div>
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">F_min</div>
                  <div className="font-medium">{money(internal.F_min, formatNumber)}</div>
                </div>
              </div>
            ) : null}

            {internal?.breakdown?.length ? (
              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50">
                    <tr>
                      <th className="text-start p-2">{t('service')}</th>
                      <th className="text-end p-2">U</th>
                      <th className="text-end p-2">M</th>
                      <th className="text-end p-2">{t('monthlyShare')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {internal.breakdown.map((row) => (
                      <tr key={row.id} className="border-t">
                        <td className="p-2">{row.name}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.U, formatNumber)}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.M, formatNumber)}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.monthly_share, formatNumber)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}

            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="secondary" onClick={() => void handleSaveQuote()}>
                <Save className="h-4 w-4" />
                {t('saveQuote')}
              </Button>
              <Button type="button" onClick={() => void handleLock()}>
                <Lock className="h-4 w-4" />
                {t('lockFp')}
              </Button>
              <Button type="button" variant="outline" onClick={() => void copyShare()}>
                <Link2 className="h-4 w-4" />
                {t('copyLink')}
              </Button>
              <Button type="button" onClick={() => void handleContract()}>
                {t('createContract')}
              </Button>
            </div>
            {shareUrl ? (
              <p className="text-xs text-muted-foreground break-all dir-ltr text-left">{shareUrl}</p>
            ) : null}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
