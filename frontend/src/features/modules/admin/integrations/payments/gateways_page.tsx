'use client';

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Banknote,
  CalendarClock,
  Check,
  CheckCircle2,
  Copy,
  CreditCard,
  ExternalLink,
  FlaskConical,
  KeyRound,
  Link2,
  Loader2,
  Percent,
  PlugZap,
  RotateCcw,
  Save,
  ShieldCheck,
  XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  getPaymentGateways,
  savePaymentGateways,
  testPaymentGateway,
  type PaymentField,
  type PaymentGatewaySettings,
} from '@/lib/api/payments';
import { useLocale } from '@/hooks/use-locale-next';
import { cn } from '@/lib/utils';

type FieldState = PaymentField & { input: string };

type FormState = {
  code: string;
  label_fa: string;
  label_en: string;
  supports: string[];
  enabled: boolean;
  sandbox: boolean;
  cash_enabled: boolean;
  installment_enabled: boolean;
  fee_percent: string;
  apply_fee_on_cash: boolean;
  callback_base_url: string;
  base_url: string;
  ticket_type_cash: string;
  ticket_type_installment: string;
  notes_fa: string;
  local_simulation: boolean;
  live_ready: boolean;
  required_live: string[];
  default_urls: { sandbox: string; production: string };
  callback_url: string;
  fields: FieldState[];
};

type TestState = { ok: boolean; message: string } | null;

const SAMPLE = 10_000_000;

const BRAND: Record<string, { bg: string; fg: string; initials: string }> = {
  zarinpal: { bg: 'bg-amber-100 dark:bg-amber-500/15', fg: 'text-amber-700 dark:text-amber-300', initials: 'ZP' },
  snappay: { bg: 'bg-sky-100 dark:bg-sky-500/15', fg: 'text-sky-700 dark:text-sky-300', initials: 'SP' },
  digipay: { bg: 'bg-rose-100 dark:bg-rose-500/15', fg: 'text-rose-700 dark:text-rose-300', initials: 'DP' },
  torobpay: { bg: 'bg-violet-100 dark:bg-violet-500/15', fg: 'text-violet-700 dark:text-violet-300', initials: 'TP' },
};

function toForm(row: PaymentGatewaySettings): FormState {
  return {
    code: row.code,
    label_fa: row.label_fa,
    label_en: row.label_en,
    supports: row.supports ?? [],
    enabled: row.enabled,
    sandbox: row.sandbox,
    cash_enabled: row.cash_enabled,
    installment_enabled: row.installment_enabled,
    fee_percent: String(row.fee_percent ?? 0),
    apply_fee_on_cash: row.apply_fee_on_cash,
    callback_base_url: row.callback_base_url ?? '',
    base_url: row.base_url ?? '',
    ticket_type_cash: String(row.ticket_type_cash ?? 0),
    ticket_type_installment: String(row.ticket_type_installment ?? 13),
    notes_fa: row.notes_fa ?? '',
    local_simulation: row.local_simulation,
    live_ready: row.live_ready ?? false,
    required_live: row.required_live ?? [],
    default_urls: row.default_urls ?? { sandbox: '', production: '' },
    callback_url: row.callback_url ?? '',
    fields: (row.fields ?? []).map((field) => ({ ...field, input: '' })),
  };
}

function signature(row: FormState) {
  return JSON.stringify({
    e: row.enabled, s: row.sandbox, c: row.cash_enabled, i: row.installment_enabled, f: row.fee_percent,
    a: row.apply_fee_on_cash, cb: row.callback_base_url.trim(), b: row.base_url.trim(),
    tc: row.ticket_type_cash, ti: row.ticket_type_installment, x: row.fields.map((f) => f.input.trim()),
  });
}

function Section({ icon, title, hint, children }: { icon: ReactNode; title: string; hint?: string; children: ReactNode }) {
  return (
    <section className="min-w-0 space-y-3">
      <div className="flex items-start gap-2">
        <span className="mt-0.5 text-muted-foreground">{icon}</span>
        <div className="min-w-0">
          <h3 className="text-sm font-semibold">{title}</h3>
          {hint ? <p className="text-xs leading-5 text-muted-foreground">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

function Pill({ tone, children }: { tone: 'green' | 'gray' | 'amber' | 'blue' | 'red'; children: ReactNode }) {
  const tones = {
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    gray: 'bg-muted text-muted-foreground ring-border',
    amber: 'bg-amber-50 text-amber-800 ring-amber-600/25 dark:bg-amber-500/10 dark:text-amber-300',
    blue: 'bg-[#0054ff]/10 text-[#0054ff] ring-[#0054ff]/20 dark:text-[#6f9bff]',
    red: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
  } as const;
  return (
    <span className={cn('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', tones[tone])}>
      {children}
    </span>
  );
}

function FieldError({ message }: { message?: string }) {
  return message ? <p className="text-xs text-destructive">{message}</p> : null;
}

export function PaymentGatewaysPage() {
  const t = useTranslations('payments');
  const tg = useTranslations('payments.ui');
  const tNav = useTranslations();
  const { formatNumber, formatDigits, locale } = useLocale();
  const [forms, setForms] = useState<FormState[]>([]);
  const [baseline, setBaseline] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState<string | null>(null);
  const [tests, setTests] = useState<Record<string, TestState>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [saveError, setSaveError] = useState<string | null>(null);

  const hydrate = useCallback((rows: PaymentGatewaySettings[]) => {
    const next = rows.map(toForm);
    setForms(next);
    setBaseline(Object.fromEntries(next.map((row) => [row.code, signature(row)])));
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      hydrate(await getPaymentGateways());
    } catch (err) {
      setLoadError(getAxiosMessage(err) || tg('loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [hydrate, tg]);

  useEffect(() => {
    void load();
  }, [load]);

  const update = (code: string, patch: Partial<FormState>) => {
    setForms((prev) => prev.map((row) => (row.code === code ? { ...row, ...patch } : row)));
  };

  const updateField = (code: string, key: string, input: string) => {
    setForms((prev) => prev.map((row) => (row.code !== code ? row : {
      ...row,
      fields: row.fields.map((field) => (field.key === key ? { ...field, input } : field)),
    })));
  };

  const dirtyCodes = useMemo(
    () => forms.filter((row) => baseline[row.code] !== signature(row)).map((row) => row.code),
    [forms, baseline],
  );

  const clientErrors = useMemo(() => {
    const errors: Record<string, string> = {};
    for (const row of forms) {
      const fee = Number(row.fee_percent);
      if (row.fee_percent.trim() === '' || !Number.isFinite(fee) || fee < 0 || fee > 100) {
        errors[`${row.code}.fee_percent`] = t('feeInvalid');
      }
      if (row.enabled && !row.cash_enabled && !row.installment_enabled) {
        errors[`${row.code}.mode`] = t('modeRequired');
      }
      for (const key of ['callback_base_url', 'base_url'] as const) {
        const value = row[key].trim();
        if (value && !/^https?:\/\/[^\s]+$/i.test(value)) errors[`${row.code}.${key}`] = t('urlInvalid');
      }
      if (row.enabled && !row.sandbox) {
        for (const field of row.fields) {
          if (row.required_live.includes(field.key) && !field.configured && !field.input.trim()) {
            errors[`${row.code}.${field.key}`] = tg('requiredLive');
          }
        }
      }
      if (row.code === 'digipay') {
        for (const key of ['ticket_type_cash', 'ticket_type_installment'] as const) {
          if (!/^\d{1,3}$/.test(row[key].trim())) errors[`${row.code}.${key}`] = tg('ticketInvalid');
        }
      }
    }
    return errors;
  }, [forms, t, tg]);

  const stats = useMemo(() => {
    const active = forms.filter((row) => row.enabled);
    return {
      active: active.length,
      total: forms.length,
      cash: active.filter((row) => row.cash_enabled && row.supports.includes('cash')).length,
      installment: active.filter((row) => row.installment_enabled && row.supports.includes('installment')).length,
      simulated: active.filter((row) => row.local_simulation),
      live: active.filter((row) => !row.sandbox).length,
    };
  }, [forms]);

  const reset = () => {
    setFieldErrors({});
    setSaveError(null);
    void load();
  };

  const save = async () => {
    setSaveError(null);
    if (Object.keys(clientErrors).length) {
      setFieldErrors(clientErrors);
      setSaveError(tg('fixErrors'));
      return;
    }
    setSaving(true);
    setFieldErrors({});
    try {
      const gateways: Record<string, Record<string, unknown>> = {};
      for (const row of forms) {
        const payload: Record<string, unknown> = {
          enabled: row.enabled,
          sandbox: row.sandbox,
          cash_enabled: row.supports.includes('cash') && row.cash_enabled,
          installment_enabled: row.supports.includes('installment') && row.installment_enabled,
          fee_percent: Number(row.fee_percent),
          apply_fee_on_cash: row.apply_fee_on_cash,
          callback_base_url: row.callback_base_url.trim(),
          base_url: row.base_url.trim(),
        };
        if (row.code === 'digipay') {
          payload.ticket_type_cash = Number(row.ticket_type_cash);
          payload.ticket_type_installment = Number(row.ticket_type_installment);
        }
        for (const field of row.fields) {
          const value = field.input.trim();
          if (value) payload[field.key] = value;
        }
        gateways[row.code] = payload;
      }
      hydrate(await savePaymentGateways(gateways));
      setTests({});
      toast.success(t('saved'));
    } catch (err) {
      const data = (err as { response?: { data?: { errors?: Record<string, string | string[]> } } })?.response?.data;
      if (data?.errors) {
        setFieldErrors(Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])));
      }
      setSaveError(getAxiosMessage(err) || tg('saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  const test = async (code: string) => {
    setTesting(code);
    try {
      const result = await testPaymentGateway(code);
      setTests((prev) => ({ ...prev, [code]: { ok: result.ok !== false, message: result.message || t('testOk') } }));
    } catch (err) {
      setTests((prev) => ({ ...prev, [code]: { ok: false, message: getAxiosMessage(err) || tg('testFailed') } }));
    } finally {
      setTesting(null);
    }
  };

  const copy = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value);
      toast.success(tg('copied'));
    } catch {
      toast.error(tg('copyFailed'));
    }
  };

  const errorOf = (code: string, key: string) => fieldErrors[`${code}.${key}`];

  if (loading && !forms.length) {
    return (
      <div className="space-y-4" dir="auto">
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => <div key={i} className="h-20 animate-pulse rounded-xl border bg-muted/40" />)}
        </div>
        {[0, 1].map((i) => <div key={i} className="h-72 animate-pulse rounded-xl border bg-muted/30" />)}
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-8 text-center">
        <XCircle className="size-8 text-destructive" />
        <p className="text-sm">{loadError}</p>
        <Button variant="outline" onClick={() => void load()}>{tg('retry')}</Button>
      </div>
    );
  }

  return (
    <div className="wd-payments min-w-0 space-y-5 pb-24">
      {/* Intro + KPIs */}
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <p className="max-w-3xl text-sm leading-6 text-muted-foreground">{tg('lead')}</p>
        <div className="flex flex-wrap gap-2">
          <Button asChild variant="outline" size="sm">
            <Link href="/dashboard/admin/integrations/payments/checkout">
              <CreditCard className="size-4" />
              {tg('transactions')}
            </Link>
          </Button>
          <Button asChild variant="outline" size="sm">
            <Link href="/dashboard/sales/storefront">
              <ExternalLink className="size-4" />
              {tg('storefrontSettings')}
            </Link>
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          { label: tg('kpiActive'), value: `${formatNumber(stats.active)} / ${formatNumber(stats.total)}`, icon: <PlugZap className="size-4" /> },
          { label: tg('kpiCash'), value: formatNumber(stats.cash), icon: <Banknote className="size-4" /> },
          { label: tg('kpiInstallment'), value: formatNumber(stats.installment), icon: <CalendarClock className="size-4" /> },
          { label: tg('kpiLive'), value: formatNumber(stats.live), icon: <ShieldCheck className="size-4" /> },
        ].map((kpi) => (
          <div key={kpi.label} className="min-w-0 rounded-xl border bg-card p-3 sm:p-4">
            <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
              <span className="truncate">{kpi.label}</span>
              <span className="text-[#0054ff]">{kpi.icon}</span>
            </div>
            <div className="mt-1.5 text-xl font-semibold tabular-nums">{kpi.value}</div>
          </div>
        ))}
      </div>

      {stats.active === 0 ? (
        <div className="flex items-start gap-3 rounded-xl border border-[#0054ff]/25 bg-[#0054ff]/5 p-4 text-sm">
          <PlugZap className="mt-0.5 size-5 shrink-0 text-[#0054ff]" />
          <div className="space-y-1">
            <p className="font-medium">{tg('noneActiveTitle')}</p>
            <p className="text-muted-foreground">{tg('noneActiveBody')}</p>
          </div>
        </div>
      ) : null}

      {stats.simulated.length ? (
        <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-500/10">
          <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600" />
          <div className="space-y-1">
            <p className="font-medium text-amber-900 dark:text-amber-200">{tg('simWarnTitle')}</p>
            <p className="text-amber-900/80 dark:text-amber-200/80">
              {tg('simWarnBody', { list: stats.simulated.map((row) => (locale === 'fa' ? row.label_fa : row.label_en)).join('، ') })}
            </p>
          </div>
        </div>
      ) : null}

      {/* Gateways */}
      <div className="grid gap-4 2xl:grid-cols-2">
        {forms.map((row) => {
          const brand = BRAND[row.code] ?? { bg: 'bg-muted', fg: 'text-foreground', initials: row.code.slice(0, 2).toUpperCase() };
          const label = locale === 'fa' ? row.label_fa : row.label_en;
          const percent = Number(row.fee_percent);
          const safePercent = Number.isFinite(percent) ? percent : 0;
          const supportsCash = row.supports.includes('cash');
          const supportsInstallment = row.supports.includes('installment');
          const dirty = dirtyCodes.includes(row.code);
          const testResult = tests[row.code];
          const defaultBase = row.sandbox ? row.default_urls.sandbox : row.default_urls.production;
          const modeError = errorOf(row.code, 'mode');
          const modeCards = [
            { key: 'cash_enabled' as const, supported: supportsCash, on: row.cash_enabled, title: t('cash'), desc: tg('cashDesc'), icon: <Banknote className="size-4" /> },
            { key: 'installment_enabled' as const, supported: supportsInstallment, on: row.installment_enabled, title: t('installment'), desc: tg('installmentDesc'), icon: <CalendarClock className="size-4" /> },
          ];
          const feeCash = row.apply_fee_on_cash ? Math.round(SAMPLE * safePercent / 100) : 0;
          const feeInst = Math.round(SAMPLE * safePercent / 100);

          return (
            <article
              key={row.code}
              className={cn(
                'min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm transition-colors',
                row.enabled ? 'border-[#0054ff]/30' : 'border-border',
              )}
            >
              {/* Header */}
              <header className="flex flex-wrap items-center gap-3 border-b bg-muted/20 px-4 py-3 sm:px-5">
                <div className={cn('flex size-11 shrink-0 items-center justify-center rounded-lg text-sm font-bold', brand.bg, brand.fg)} dir="ltr">
                  {brand.initials}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 className="text-base font-semibold">{label}</h2>
                    {dirty ? <Pill tone="blue">{tg('unsaved')}</Pill> : null}
                  </div>
                  <div className="mt-1 flex flex-wrap gap-1.5">
                    {supportsCash ? <Pill tone="gray"><Banknote className="size-3" />{t('cash')}</Pill> : null}
                    {supportsInstallment ? <Pill tone="gray"><CalendarClock className="size-3" />{t('installment')}</Pill> : null}
                    {row.sandbox ? <Pill tone="amber"><FlaskConical className="size-3" />{t('sandbox')}</Pill> : <Pill tone="green"><ShieldCheck className="size-3" />{t('production')}</Pill>}
                    {row.local_simulation ? <Pill tone="amber">{tg('simulator')}</Pill> : null}
                    {row.live_ready ? <Pill tone="green"><KeyRound className="size-3" />{tg('credsOk')}</Pill> : <Pill tone="red"><KeyRound className="size-3" />{tg('credsMissing')}</Pill>}
                  </div>
                </div>
                <label htmlFor={`${row.code}-enabled`} className="flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-sm">
                  <span className={cn('font-medium', row.enabled ? 'text-[#0054ff]' : 'text-muted-foreground')}>
                    {row.enabled ? t('enabled') : t('disabled')}
                  </span>
                  <Switch id={`${row.code}-enabled`} checked={row.enabled} onCheckedChange={(checked) => update(row.code, { enabled: checked })} />
                </label>
              </header>

              <div className="space-y-6 px-4 py-5 sm:px-5">
                {row.notes_fa && locale === 'fa' ? (
                  <p className="rounded-lg bg-muted/40 px-3 py-2 text-xs leading-6 text-muted-foreground">{row.notes_fa}</p>
                ) : null}

                {/* Modes */}
                <Section icon={<CreditCard className="size-4" />} title={tg('modesTitle')} hint={tg('modesHint')}>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {modeCards.map((mode) => (
                      <button
                        key={mode.key}
                        type="button"
                        disabled={!mode.supported}
                        aria-pressed={mode.on && mode.supported}
                        onClick={() => update(row.code, { [mode.key]: !mode.on } as Partial<FormState>)}
                        className={cn(
                          'flex min-w-0 items-start gap-3 rounded-lg border p-3 text-start transition-colors',
                          !mode.supported && 'cursor-not-allowed opacity-50',
                          mode.supported && mode.on ? 'border-[#0054ff] bg-[#0054ff]/5' : 'hover:bg-muted/40',
                        )}
                      >
                        <span className={cn('mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border', mode.supported && mode.on ? 'border-[#0054ff] bg-[#0054ff] text-white' : 'bg-background')}>
                          {mode.supported && mode.on ? <Check className="size-3.5" /> : null}
                        </span>
                        <span className="min-w-0">
                          <span className="flex items-center gap-1.5 text-sm font-medium">{mode.icon}{mode.title}</span>
                          <span className="mt-0.5 block text-xs text-muted-foreground">{mode.supported ? mode.desc : tg('notSupported')}</span>
                        </span>
                      </button>
                    ))}
                  </div>
                  <FieldError message={modeError} />
                </Section>

                {/* Environment */}
                <Section icon={<FlaskConical className="size-4" />} title={t('environment')} hint={row.sandbox ? tg('sandboxHint') : tg('productionHint')}>
                  <div className="inline-flex rounded-lg border bg-muted/30 p-1" role="radiogroup">
                    {[{ v: true, l: t('sandbox') }, { v: false, l: t('production') }].map((opt) => (
                      <button
                        key={String(opt.v)}
                        type="button"
                        role="radio"
                        aria-checked={row.sandbox === opt.v}
                        onClick={() => update(row.code, { sandbox: opt.v })}
                        className={cn(
                          'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                          row.sandbox === opt.v ? 'bg-background text-[#0054ff] shadow-sm' : 'text-muted-foreground hover:text-foreground',
                        )}
                      >
                        {opt.l}
                      </button>
                    ))}
                  </div>
                </Section>

                {/* Credentials */}
                <Section icon={<KeyRound className="size-4" />} title={tg('credsTitle')} hint={tg('credsHint')}>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {row.fields.map((field) => (
                      <div key={field.key} className="min-w-0 space-y-1.5">
                        <Label htmlFor={`${row.code}-${field.key}`} className="flex items-center justify-between gap-2 text-xs">
                          <span className="truncate">{locale === 'fa' ? field.label_fa : field.label_en}</span>
                          {field.configured ? (
                            <span className="inline-flex items-center gap-1 text-[11px] text-emerald-600" dir="ltr"><CheckCircle2 className="size-3" />{field.masked}</span>
                          ) : row.required_live.includes(field.key) ? (
                            <span className="text-[11px] text-muted-foreground">{tg('requiredForLive')}</span>
                          ) : null}
                        </Label>
                        <Input
                          id={`${row.code}-${field.key}`}
                          type={field.secret ? 'password' : 'text'}
                          autoComplete="new-password"
                          dir="ltr"
                          className="h-9 font-mono text-sm"
                          placeholder={field.configured ? tg('keepValue') : ''}
                          value={field.input}
                          onChange={(e) => updateField(row.code, field.key, e.target.value)}
                          aria-invalid={Boolean(errorOf(row.code, field.key))}
                        />
                        <FieldError message={errorOf(row.code, field.key)} />
                      </div>
                    ))}
                    {row.code === 'digipay' ? (
                      <>
                        {(['ticket_type_cash', 'ticket_type_installment'] as const).map((key) => (
                          <div key={key} className="min-w-0 space-y-1.5">
                            <Label htmlFor={`${row.code}-${key}`} className="text-xs">{tg(key === 'ticket_type_cash' ? 'ticketCash' : 'ticketInstallment')}</Label>
                            <Input
                              id={`${row.code}-${key}`}
                              inputMode="numeric"
                              dir="ltr"
                              className="h-9 font-mono text-sm"
                              value={row[key]}
                              onChange={(e) => update(row.code, { [key]: e.target.value } as Partial<FormState>)}
                            />
                            <FieldError message={errorOf(row.code, key)} />
                          </div>
                        ))}
                      </>
                    ) : null}
                  </div>
                </Section>

                {/* URLs */}
                <Section icon={<Link2 className="size-4" />} title={tg('urlsTitle')}>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="min-w-0 space-y-1.5">
                      <Label htmlFor={`${row.code}-base`} className="text-xs">{t('baseUrl')}</Label>
                      <Input
                        id={`${row.code}-base`}
                        dir="ltr"
                        className="h-9 text-sm"
                        value={row.base_url}
                        onChange={(e) => update(row.code, { base_url: e.target.value })}
                        placeholder={defaultBase || 'https://'}
                      />
                      <p className="text-[11px] text-muted-foreground">{defaultBase ? tg('baseDefault') : tg('baseNoDefault')}</p>
                      <FieldError message={errorOf(row.code, 'base_url')} />
                    </div>
                    <div className="min-w-0 space-y-1.5">
                      <Label htmlFor={`${row.code}-callback`} className="text-xs">{t('callbackBase')}</Label>
                      <Input
                        id={`${row.code}-callback`}
                        dir="ltr"
                        className="h-9 text-sm"
                        value={row.callback_base_url}
                        onChange={(e) => update(row.code, { callback_base_url: e.target.value })}
                        placeholder="https://webina.dev"
                      />
                      <p className="text-[11px] text-muted-foreground">{tg('callbackBaseHint')}</p>
                      <FieldError message={errorOf(row.code, 'callback_base_url')} />
                    </div>
                  </div>
                  {row.callback_url ? (
                    <div className="min-w-0 space-y-1.5">
                      <Label className="text-xs">{tg('callbackUrl')}</Label>
                      <div className="flex min-w-0 items-center gap-2 rounded-lg border bg-muted/30 px-3 py-2">
                        <code className="min-w-0 flex-1 truncate text-xs" dir="ltr" title={row.callback_url}>{row.callback_url}</code>
                        <Button type="button" variant="ghost" size="icon" className="size-7 shrink-0" onClick={() => void copy(row.callback_url)} aria-label={tg('copy')}>
                          <Copy className="size-3.5" />
                        </Button>
                      </div>
                      <p className="text-[11px] text-muted-foreground">{tg('callbackUrlHint')}</p>
                    </div>
                  ) : null}
                </Section>

                {/* Fee */}
                <Section icon={<Percent className="size-4" />} title={tg('feeTitle')} hint={tg('feeHint')}>
                  <div className="grid gap-3 sm:grid-cols-[minmax(0,10rem)_1fr] sm:items-start">
                    <div className="min-w-0 space-y-1.5">
                      <Label htmlFor={`${row.code}-fee`} className="text-xs">{t('feePercent')}</Label>
                      <div className="relative">
                        <Input
                          id={`${row.code}-fee`}
                          inputMode="decimal"
                          dir="ltr"
                          className="h-9 pe-8 text-sm"
                          value={row.fee_percent}
                          onChange={(e) => update(row.code, { fee_percent: e.target.value })}
                          aria-invalid={Boolean(errorOf(row.code, 'fee_percent'))}
                        />
                        <span className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-muted-foreground">%</span>
                      </div>
                      <FieldError message={errorOf(row.code, 'fee_percent')} />
                    </div>
                    <div className="min-w-0 space-y-2">
                      <label className={cn('flex items-center gap-2 text-sm', !supportsCash && 'opacity-50')}>
                        <Switch
                          checked={row.apply_fee_on_cash}
                          disabled={!supportsCash}
                          onCheckedChange={(checked) => update(row.code, { apply_fee_on_cash: checked })}
                        />
                        {t('feeOnCash')}
                      </label>
                      <div className="overflow-hidden rounded-lg border text-xs">
                        <div className="grid grid-cols-3 bg-muted/40 px-3 py-1.5 font-medium text-muted-foreground">
                          <span>{tg('feeSample', { amount: formatNumber(SAMPLE) })}</span>
                          <span className="text-center">{t('feeAmount')}</span>
                          <span className="text-end">{t('totalCharged')}</span>
                        </div>
                        {[
                          supportsCash ? { l: t('cash'), fee: feeCash } : null,
                          supportsInstallment ? { l: t('installment'), fee: feeInst } : null,
                        ].filter(Boolean).map((r) => (
                          <div key={r!.l} className="grid grid-cols-3 border-t px-3 py-1.5 tabular-nums">
                            <span>{r!.l}</span>
                            <span className="text-center">{formatNumber(r!.fee)}</span>
                            <span className="text-end font-medium">{formatNumber(SAMPLE + r!.fee)}</span>
                          </div>
                        ))}
                      </div>
                      <p className="text-[11px] text-muted-foreground">{tg('rialNote')}</p>
                    </div>
                  </div>
                </Section>
              </div>

              {/* Footer: test */}
              <footer className="flex flex-col gap-2 border-t bg-muted/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="min-w-0 text-xs">
                  {testResult ? (
                    <span className={cn('inline-flex items-start gap-1.5', testResult.ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-destructive')}>
                      {testResult.ok ? <CheckCircle2 className="mt-0.5 size-3.5 shrink-0" /> : <XCircle className="mt-0.5 size-3.5 shrink-0" />}
                      <span className="leading-5">{formatDigits(testResult.message)}</span>
                    </span>
                  ) : (
                    <span className="text-muted-foreground">{dirty ? tg('testAfterSave') : tg('testHint')}</span>
                  )}
                </div>
                <Button type="button" variant="outline" size="sm" className="shrink-0" disabled={testing === row.code || dirty} onClick={() => void test(row.code)}>
                  {testing === row.code ? <Loader2 className="size-4 animate-spin" /> : <PlugZap className="size-4" />}
                  {t('testConnection')}
                </Button>
              </footer>
            </article>
          );
        })}
      </div>

      {/* Sticky save bar */}
      <div
        className={cn(
          'sticky bottom-3 z-20 flex flex-col gap-2 rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur transition-all sm:flex-row sm:items-center sm:justify-between',
          dirtyCodes.length || saveError ? 'opacity-100' : 'pointer-events-none translate-y-2 opacity-0',
        )}
      >
        <div className="min-w-0 text-sm">
          {saveError ? (
            <span className="inline-flex items-center gap-1.5 text-destructive"><XCircle className="size-4 shrink-0" />{saveError}</span>
          ) : (
            <span className="text-muted-foreground">{tg('dirtyCount', { count: formatNumber(dirtyCodes.length) })}</span>
          )}
        </div>
        <div className="flex gap-2">
          <Button type="button" variant="ghost" size="sm" disabled={saving} onClick={reset}>
            <RotateCcw className="size-4" />
            {tg('discard')}
          </Button>
          <Button type="button" size="sm" className="bg-[#0054ff] text-white hover:bg-[#0047d9]" disabled={saving} onClick={() => void save()}>
            {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
            {saving ? tNav('common.loading') : t('save')}
          </Button>
        </div>
      </div>
    </div>
  );
}
