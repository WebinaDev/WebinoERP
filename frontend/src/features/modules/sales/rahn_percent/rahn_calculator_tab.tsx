'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { ArrowLeft, ArrowRight, Link2, Loader2, Lock, Save } from 'lucide-react';
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
import {
  RahnCategorizedSummary,
  RahnChoiceCard,
  RahnWizardProgress,
  defaultSelectedIds,
  groupCatalogByCategory,
  toggleCatalogSelection,
  type RahnWizardStep,
} from './rahn_wizard';
import {
  RAHN_API,
  RAHN_DURATION_OPTIONS,
  clampRahnDuration,
  type RahnCalcResponse,
  type RahnCatalogItem,
  type RahnSettings,
} from './types';

type Props = { settings: RahnSettings };

function money(n: number, formatNumber: (n: number) => string) {
  return formatNumber(Math.round(n));
}

export function RahnCalculatorTab({ settings }: Props) {
  const t = useTranslations('sales.rahn');
  const { formatNumber } = useLocale();
  const durationOptions = settings.duration_options?.length
    ? settings.duration_options
    : [...RAHN_DURATION_OPTIONS];
  const activeCatalog = useMemo(() => settings.catalog.filter((c) => c.active !== false), [settings.catalog]);
  const topics = useMemo(
    () => (settings.topics ?? []).filter((x) => x.active !== false).sort((a, b) => a.sort_order - b.sort_order),
    [settings.topics],
  );
  const domains = useMemo(
    () => (settings.domains ?? []).filter((x) => x.active !== false).sort((a, b) => a.sort_order - b.sort_order),
    [settings.domains],
  );
  const categories = useMemo(
    () => (settings.categories ?? []).filter((x) => x.active !== false).sort((a, b) => a.sort_order - b.sort_order),
    [settings.categories],
  );

  const [step, setStep] = useState<RahnWizardStep>('business');
  const [businessName, setBusinessName] = useState('');
  const [topicId, setTopicId] = useState('');
  const [domainId, setDomainId] = useState('');
  const [selected, setSelected] = useState<string[]>(() => defaultSelectedIds(activeCatalog));
  const [sHat, setSHat] = useState(settings.s_hat_default);
  const [duration, setDuration] = useState(() => clampRahnDuration(settings.T, durationOptions));
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

  const domainOptions = useMemo(
    () => domains.filter((d) => !topicId || d.topic_id === topicId),
    [domains, topicId],
  );

  const selectedDomain = domains.find((d) => d.id === domainId);
  const selectedItems = useMemo(
    () => activeCatalog.filter((c) => selected.includes(c.id)),
    [activeCatalog, selected],
  );

  const steps = useMemo(
    () =>
      [
        { id: 'business' as const, label: t('wizard.stepBusiness') },
        { id: 'topic' as const, label: t('wizard.stepTopic') },
        { id: 'domain' as const, label: t('wizard.stepDomain') },
        { id: 'services' as const, label: t('wizard.stepServices') },
        { id: 'deal' as const, label: t('wizard.stepDeal') },
        { id: 'summary' as const, label: t('wizard.stepSummary') },
      ] satisfies Array<{ id: RahnWizardStep; label: string }>,
    [t],
  );

  const runCalc = useCallback(async () => {
    setLoading(true);
    try {
      const body: Record<string, unknown> = {
        selected_ids: selected,
        s_hat: sHat,
        T: duration,
        mode,
        business_name: businessName,
        topic_id: topicId,
        domain_id: domainId,
      };
      if (mode === 'from_p') body.p_percent = pPercent;
      else body.F_wanted = fWanted;

      const res = await apiClient.post(`${RAHN_API}/calculate`, body);
      const raw = res.data as RahnCalcResponse | { data: RahnCalcResponse };
      const data = ('data' in raw && raw.data && !('lock' in raw) ? raw.data : raw) as RahnCalcResponse;
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
  }, [selected, sHat, duration, mode, pPercent, fWanted, businessName, topicId, domainId, t]);

  useEffect(() => {
    if (step !== 'deal' && step !== 'summary') return;
    const timer = window.setTimeout(() => {
      void runCalc();
    }, 250);
    return () => window.clearTimeout(timer);
  }, [runCalc, step]);

  useEffect(() => {
    void apiClient
      .get(`${RAHN_API}/customers`)
      .then((res) => {
        const body = res.data as { customers?: { id: number; display_name: string }[]; data?: { customers?: { id: number; display_name: string }[] } };
        const list = body?.customers ?? body?.data?.customers;
        if (list) setCustomers(list);
      })
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!domainId || !selectedDomain) return;
    setPPercent(selectedDomain.p_suggest * 100);
    setMode('from_p');
  }, [domainId, selectedDomain]);

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
    title: title || (businessName ? `${businessName}` : ''),
    customer_id: customerId ? Number(customerId) : 0,
    selected_ids: selected,
    s_hat: sHat,
    T: duration,
    mode,
    p_percent: pPercent,
    F_wanted: fWanted,
    business_name: businessName,
    topic_id: topicId,
    domain_id: domainId,
  });

  const unwrapQuote = (resData: unknown) => {
    const body = resData as {
      quote?: { id: number; share_url: string };
      calculation?: RahnCalcResponse;
      message?: string;
      data?: { quote?: { id: number; share_url: string }; calculation?: RahnCalcResponse; message?: string };
    };
    return body.data ?? body;
  };

  const handleSaveQuote = async () => {
    try {
      const res = await apiClient.post(`${RAHN_API}/quotes`, calcBody());
      const data = unwrapQuote(res.data);
      if (!data.quote) throw new Error('no quote');
      setQuoteId(data.quote.id);
      setShareUrl(data.quote.share_url);
      if (data.calculation) setCalc(data.calculation);
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
        const savedData = unwrapQuote(saved.data);
        id = savedData.quote?.id ?? null;
        if (!id) throw new Error('no id');
        setQuoteId(id);
        setShareUrl(savedData.quote?.share_url ?? null);
      }
      const res = await apiClient.post(`${RAHN_API}/quotes/${id}/lock`, calcBody());
      const data = unwrapQuote(res.data);
      if (data.calculation) setCalc(data.calculation);
      if (data.quote?.share_url) setShareUrl(data.quote.share_url);
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
        contract_title: title || businessName,
      });
      const body = res.data as { message?: string; data?: { message?: string } };
      toast.success(body.data?.message || body.message || t('contractCreated'));
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

  const stepIndex = steps.findIndex((s) => s.id === step);
  const goNext = () => {
    if (step === 'business' && !businessName.trim()) {
      toast.error(t('wizard.businessRequired'));
      return;
    }
    if (step === 'topic' && !topicId) {
      toast.error(t('wizard.topicRequired'));
      return;
    }
    if (step === 'domain' && !domainId) {
      toast.error(t('wizard.domainRequired'));
      return;
    }
    const next = steps[stepIndex + 1];
    if (next) setStep(next.id);
  };
  const goPrev = () => {
    const prev = steps[stepIndex - 1];
    if (prev) setStep(prev.id);
  };

  const serviceGroups = groupCatalogByCategory(activeCatalog, categories);

  return (
    <div className="space-y-6 text-start" dir="rtl">
      <RahnWizardProgress steps={steps} current={step} onSelect={setStep} />

      {step === 'business' ? (
        <Card className="overflow-hidden border-primary/20 bg-gradient-to-bl from-primary/10 via-background to-background">
          <CardHeader>
            <CardTitle className="text-xl">{t('wizard.businessTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
              <Label>{t('wizard.businessName')}</Label>
              <Input
                value={businessName}
                onChange={(e) => setBusinessName(e.target.value)}
                placeholder={t('wizard.businessPlaceholder')}
                className="text-start"
              />
            </div>
            <div className="grid gap-2">
              <Label>{t('quoteTitle')}</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder={businessName || t('quoteTitle')} />
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
      ) : null}

      {step === 'topic' ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {topics.map((topic) => (
            <RahnChoiceCard
              key={topic.id}
              selected={topicId === topic.id}
              title={topic.name}
              description={topic.description}
              onClick={() => {
                setTopicId(topic.id);
                if (domainId && !domains.some((d) => d.id === domainId && d.topic_id === topic.id)) {
                  setDomainId('');
                }
              }}
            />
          ))}
        </div>
      ) : null}

      {step === 'domain' ? (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">{t('wizard.domainHint')}</p>
          <div className="grid gap-3 sm:grid-cols-2">
            {domainOptions.map((domain) => (
              <RahnChoiceCard
                key={domain.id}
                selected={domainId === domain.id}
                title={domain.name}
                description={domain.description}
                badge={`${formatNumber(Math.round(domain.p_suggest * 1000) / 10)}%`}
                onClick={() => setDomainId(domain.id)}
              />
            ))}
          </div>
        </div>
      ) : null}

      {step === 'services' ? (
        <div className="space-y-5">
          {serviceGroups.map(({ category, items }) => {
            const hasGroup = items.some((i) => i.choice_group);
            return (
              <Card key={category.id}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-base">{category.name}</CardTitle>
                  {'description' in category && category.description ? (
                    <p className="text-sm text-muted-foreground">{category.description}</p>
                  ) : null}
                </CardHeader>
                <CardContent className="space-y-3">
                  {items.map((item) => {
                    const checked = selected.includes(item.id);
                    return (
                      <label
                        key={item.id}
                        className="flex cursor-pointer items-start gap-3 rounded-xl border p-3 hover:bg-muted/40"
                      >
                        <Checkbox
                          checked={checked}
                          onCheckedChange={(v) =>
                            setSelected((prev) => toggleCatalogSelection(activeCatalog, prev, item.id, !!v))
                          }
                          className="mt-0.5"
                        />
                        <div className="min-w-0 flex-1 space-y-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{item.name}</span>
                            {hasGroup && item.choice_group ? (
                              <Badge variant="outline">{t('wizard.exclusive')}</Badge>
                            ) : null}
                            {item.fee_label ? <Badge variant="secondary">{item.fee_label}</Badge> : null}
                          </div>
                          {item.description ? (
                            <p className="text-xs leading-6 text-muted-foreground">{item.description}</p>
                          ) : null}
                          <div className="text-xs text-muted-foreground">
                            {billingLabel(item)} · {money(item.amount, formatNumber)} {t('toman')}
                          </div>
                        </div>
                      </label>
                    );
                  })}
                </CardContent>
              </Card>
            );
          })}
        </div>
      ) : null}

      {step === 'deal' ? (
        <div className="grid gap-6 lg:grid-cols-5">
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle className="text-base">{t('sessionInputs')}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
              <div className="grid gap-2">
                <Label>{t('sHat')}</Label>
                <Input type="number" value={sHat} onChange={(e) => setSHat(Number(e.target.value) || 0)} dir="ltr" />
              </div>
              <div className="grid gap-2">
                <Label>{t('duration')}</Label>
                <div className="space-y-2">
                  <RahnRangeSlider
                    min={0}
                    max={durationOptions.length - 1}
                    step={1}
                    value={Math.max(0, durationOptions.indexOf(duration))}
                    onValueChange={(idx) => setDuration(durationOptions[idx] ?? durationOptions[0]!)}
                  />
                  <div className="flex justify-between text-xs text-muted-foreground">
                    {durationOptions.map((d) => (
                      <button
                        key={d}
                        type="button"
                        className={d === duration ? 'font-semibold text-foreground' : ''}
                        onClick={() => setDuration(d)}
                      >
                        {d}
                      </button>
                    ))}
                  </div>
                  <p className="text-sm font-medium">{t('wizard.durationMonths', { months: duration })}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="lg:col-span-3">
            <CardHeader className="flex flex-row items-center justify-between gap-2">
              <CardTitle className="text-base">{t('negotiator')}</CardTitle>
              {loading ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : null}
            </CardHeader>
            <CardContent className="space-y-6">
              {selectedDomain ? (
                <Alert>
                  <AlertDescription>
                    {t('wizard.domainSuggest', {
                      name: selectedDomain.name,
                      percent: formatNumber(Math.round(selectedDomain.p_suggest * 1000) / 10),
                    })}
                  </AlertDescription>
                </Alert>
              ) : null}
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
                  <span className="font-semibold tabular-nums" dir="ltr">
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
                <p className="text-xs text-muted-foreground">
                  {t('wizard.pFloorCeil', {
                    min: formatNumber(Math.round(pMinPct * 10) / 10),
                    max: formatNumber(Math.round(pMaxPct * 10) / 10),
                  })}
                </p>
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
                <p className="text-xs text-muted-foreground">{t('wizard.fixedHint')}</p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border bg-primary/5 p-4">
                  <div className="text-xs text-muted-foreground">{t('fixedMonthly')}</div>
                  <div className="mt-1 text-2xl font-bold">{money(lock?.F ?? 0, formatNumber)}</div>
                </div>
                <div className="rounded-xl border bg-emerald-500/10 p-4">
                  <div className="text-xs text-muted-foreground">{t('percentOfSales')}</div>
                  <div className="mt-1 text-2xl font-bold">
                    {formatNumber(Math.round((lock?.p_percent ?? 0) * 100) / 100)}%
                  </div>
                </div>
                <div className="rounded-xl border p-4">
                  <div className="text-xs text-muted-foreground">{t('expectedValue')}</div>
                  <div className="mt-1 text-xl font-semibold">{money(lock?.V_hat ?? 0, formatNumber)}</div>
                </div>
                <div className="rounded-xl border p-4">
                  <div className="text-xs text-muted-foreground">{t('breakeven')}</div>
                  <div className="mt-1 text-xl font-semibold">
                    {lock?.S_BE == null ? t('undefined') : money(lock.S_BE, formatNumber)}
                  </div>
                </div>
              </div>

              {calc?.clause ? (
                <div className="rounded-xl border border-dashed bg-muted/30 p-4 text-sm leading-7">{calc.clause}</div>
              ) : null}

              {internal ? (
                <div className="grid gap-2 text-sm sm:grid-cols-3">
                  <div className="rounded-lg bg-muted/50 p-3">
                    <div className="text-xs text-muted-foreground">C</div>
                    <div className="font-medium">{money(internal.C, formatNumber)}</div>
                  </div>
                  <div className="rounded-lg bg-muted/50 p-3">
                    <div className="text-xs text-muted-foreground">V*</div>
                    <div className="font-medium">{money(internal.V_star, formatNumber)}</div>
                  </div>
                  <div className="rounded-lg bg-muted/50 p-3">
                    <div className="text-xs text-muted-foreground">F_min</div>
                    <div className="font-medium">{money(internal.F_min, formatNumber)}</div>
                  </div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </div>
      ) : null}

      {step === 'summary' ? (
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('wizard.summaryTitle')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="grid gap-2 sm:grid-cols-2">
                <div>
                  <span className="text-muted-foreground">{t('wizard.businessName')}: </span>
                  {businessName || '—'}
                </div>
                <div>
                  <span className="text-muted-foreground">{t('wizard.stepDomain')}: </span>
                  {selectedDomain?.name || '—'}
                </div>
                <div>
                  <span className="text-muted-foreground">{t('duration')}: </span>
                  {t('wizard.durationMonths', { months: duration })}
                </div>
                <div>
                  <span className="text-muted-foreground">{t('negotiator')}: </span>
                  {money(lock?.F ?? 0, formatNumber)} {t('toman')} +{' '}
                  {formatNumber(Math.round((lock?.p_percent ?? 0) * 100) / 100)}%
                </div>
              </div>
            </CardContent>
          </Card>

          <RahnCategorizedSummary
            items={selectedItems}
            categories={categories}
            formatMoney={(n) => money(n, formatNumber)}
            emptyLabel={t('wizard.noServices')}
          />

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
            <p className="break-all text-start text-xs text-muted-foreground" dir="ltr">
              {shareUrl}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <Button type="button" variant="outline" disabled={stepIndex <= 0} onClick={goPrev}>
          <ArrowRight className="h-4 w-4" />
          {t('wizard.prev')}
        </Button>
        {step !== 'summary' ? (
          <Button type="button" onClick={goNext}>
            {t('wizard.next')}
            <ArrowLeft className="h-4 w-4" />
          </Button>
        ) : null}
      </div>
    </div>
  );
}
