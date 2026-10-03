'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type FieldState = PaymentField & { input: string };

type FormState = {
  code: string;
  label_fa: string;
  supports: string[];
  enabled: boolean;
  sandbox: boolean;
  cash_enabled: boolean;
  installment_enabled: boolean;
  fee_percent: string;
  apply_fee_on_cash: boolean;
  callback_base_url: string;
  base_url: string;
  notes_fa: string;
  local_simulation: boolean;
  fields: FieldState[];
};

const SAMPLE = 1_000_000;

function toForm(row: PaymentGatewaySettings): FormState {
  return {
    code: row.code,
    label_fa: row.label_fa,
    supports: row.supports ?? [],
    enabled: row.enabled,
    sandbox: row.sandbox,
    cash_enabled: row.cash_enabled,
    installment_enabled: row.installment_enabled,
    fee_percent: String(row.fee_percent ?? 0),
    apply_fee_on_cash: row.apply_fee_on_cash,
    callback_base_url: row.callback_base_url ?? '',
    base_url: row.base_url ?? '',
    notes_fa: row.notes_fa ?? '',
    local_simulation: row.local_simulation,
    fields: (row.fields ?? []).map((field) => ({ ...field, input: '' })),
  };
}

function preview(base: number, percent: number, apply: boolean) {
  const fee = apply ? Math.round(base * percent / 100) : 0;
  return { fee, total: base + fee };
}

export function PaymentGatewaysPage() {
  const t = useTranslations('payments');
  const tNav = useTranslations();
  const { formatNumber } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [forms, setForms] = useState<FormState[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const rows = await getPaymentGateways();
      setForms(rows.map(toForm));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const update = (code: string, patch: Partial<FormState>) => {
    setForms((prev) => prev.map((row) => (row.code === code ? { ...row, ...patch } : row)));
  };

  const updateField = (code: string, key: string, input: string) => {
    setForms((prev) => prev.map((row) => {
      if (row.code !== code) return row;
      return { ...row, fields: row.fields.map((field) => (field.key === key ? { ...field, input } : field)) };
    }));
  };

  const clientErrors = useMemo(() => {
    const errors: Record<string, string> = {};
    for (const row of forms) {
      const fee = Number(row.fee_percent);
      if (!Number.isFinite(fee) || fee < 0 || fee > 100) {
        errors[`${row.code}.fee_percent`] = t('feeInvalid');
      }
      if (row.enabled && !row.cash_enabled && !row.installment_enabled) {
        errors[`${row.code}.mode`] = t('modeRequired');
      }
      for (const key of ['callback_base_url', 'base_url'] as const) {
        const value = row[key].trim();
        if (value && !/^https?:\/\//i.test(value)) {
          errors[`${row.code}.${key}`] = t('urlInvalid');
        }
      }
    }
    return errors;
  }, [forms, t]);

  const save = async () => {
    if (Object.keys(clientErrors).length) {
      setFieldErrors(clientErrors);
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
          cash_enabled: row.cash_enabled,
          installment_enabled: row.installment_enabled,
          fee_percent: Number(row.fee_percent),
          apply_fee_on_cash: row.apply_fee_on_cash,
          callback_base_url: row.callback_base_url.trim(),
          base_url: row.base_url.trim(),
        };
        for (const field of row.fields) {
          const value = field.input.trim();
          if (value) payload[field.key] = value;
        }
        gateways[row.code] = payload;
      }
      const rows = await savePaymentGateways(gateways);
      setForms(rows.map(toForm));
      setSuccess(t('saved'));
    } catch (err) {
      const data = (err as { response?: { data?: { errors?: Record<string, string> } } })?.response?.data;
      if (data?.errors) setFieldErrors(data.errors);
      applyAxiosError(err, getAxiosMessage(err));
    } finally {
      setSaving(false);
    }
  };

  const test = async (code: string) => {
    setTesting(code);
    try {
      const result = await testPaymentGateway(code);
      setSuccess(result.message || t('testOk'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setTesting(null);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.paymentGateways')} description={t('settingsLead')} {...layoutProps}>
      {loading ? (
        <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
      ) : (
        <div className="space-y-4">
          {forms.map((row) => {
            const percent = Number(row.fee_percent);
            const safePercent = Number.isFinite(percent) ? percent : 0;
            const cash = preview(SAMPLE, safePercent, row.apply_fee_on_cash);
            const installment = preview(SAMPLE, safePercent, true);
            const supportsCash = row.supports.includes('cash');
            const supportsInstallment = row.supports.includes('installment');
            return (
              <Card key={row.code}>
                <CardHeader className="flex flex-row items-center justify-between gap-3">
                  <CardTitle className="text-base">{row.label_fa}</CardTitle>
                  <div className="flex items-center gap-2">
                    <Label htmlFor={`${row.code}-enabled`}>{row.enabled ? t('enabled') : t('disabled')}</Label>
                    <Switch
                      id={`${row.code}-enabled`}
                      checked={row.enabled}
                      onCheckedChange={(checked) => update(row.code, { enabled: checked })}
                    />
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-sm text-muted-foreground">{row.notes_fa}</p>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label>{t('environment')}</Label>
                      <div className="flex gap-2">
                        <Button type="button" size="sm" variant={row.sandbox ? 'default' : 'outline'} onClick={() => update(row.code, { sandbox: true })}>
                          {t('sandbox')}
                        </Button>
                        <Button type="button" size="sm" variant={!row.sandbox ? 'default' : 'outline'} onClick={() => update(row.code, { sandbox: false })}>
                          {t('production')}
                        </Button>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor={`${row.code}-fee`}>{t('feePercent')}</Label>
                      <Input
                        id={`${row.code}-fee`}
                        inputMode="decimal"
                        dir="ltr"
                        value={row.fee_percent}
                        onChange={(e) => update(row.code, { fee_percent: e.target.value })}
                      />
                      {fieldErrors[`${row.code}.fee_percent`] ? (
                        <p className="text-xs text-destructive">{fieldErrors[`${row.code}.fee_percent`]}</p>
                      ) : null}
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        className="size-4"
                        checked={row.cash_enabled}
                        disabled={!supportsCash}
                        onChange={(e) => update(row.code, { cash_enabled: e.target.checked })}
                      />
                      {t('cash')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        className="size-4"
                        checked={row.installment_enabled}
                        disabled={!supportsInstallment}
                        onChange={(e) => update(row.code, { installment_enabled: e.target.checked })}
                      />
                      {t('installment')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        className="size-4"
                        checked={row.apply_fee_on_cash}
                        onChange={(e) => update(row.code, { apply_fee_on_cash: e.target.checked })}
                      />
                      {t('feeOnCash')}
                    </label>
                  </div>
                  {fieldErrors[`${row.code}.mode`] ? (
                    <p className="text-xs text-destructive">{fieldErrors[`${row.code}.mode`]}</p>
                  ) : null}

                  <div className="rounded-md border p-3 text-sm">
                    <p className="mb-2 font-medium">{t('feePreview')}</p>
                    <p>{t('sampleBase')}: {formatNumber(SAMPLE)} {t('rial')}</p>
                    <p>{t('cash')}: {t('feeAmount')} {formatNumber(cash.fee)} / {t('totalCharged')} {formatNumber(cash.total)}</p>
                    <p>{t('installment')}: {t('feeAmount')} {formatNumber(installment.fee)} / {t('totalCharged')} {formatNumber(installment.total)}</p>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    {row.fields.map((field) => (
                      <div key={field.key} className="space-y-2">
                        <Label htmlFor={`${row.code}-${field.key}`}>{field.label_fa}</Label>
                        <Input
                          id={`${row.code}-${field.key}`}
                          type="password"
                          autoComplete="off"
                          dir="ltr"
                          className="font-mono"
                          placeholder={field.configured ? t('keepSecret') : field.label_fa}
                          value={field.input}
                          onChange={(e) => updateField(row.code, field.key, e.target.value)}
                        />
                        {field.masked ? (
                          <p className="text-xs text-muted-foreground" dir="ltr">{field.masked}</p>
                        ) : null}
                        {fieldErrors[`${row.code}.${field.key}`] ? (
                          <p className="text-xs text-destructive">{fieldErrors[`${row.code}.${field.key}`]}</p>
                        ) : null}
                      </div>
                    ))}
                    <div className="space-y-2">
                      <Label htmlFor={`${row.code}-base`}>{t('baseUrl')}</Label>
                      <Input
                        id={`${row.code}-base`}
                        dir="ltr"
                        value={row.base_url}
                        onChange={(e) => update(row.code, { base_url: e.target.value })}
                        placeholder="https://"
                      />
                      {fieldErrors[`${row.code}.base_url`] ? (
                        <p className="text-xs text-destructive">{fieldErrors[`${row.code}.base_url`]}</p>
                      ) : null}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor={`${row.code}-callback`}>{t('callbackBase')}</Label>
                      <Input
                        id={`${row.code}-callback`}
                        dir="ltr"
                        value={row.callback_base_url}
                        onChange={(e) => update(row.code, { callback_base_url: e.target.value })}
                        placeholder="https://"
                      />
                      {fieldErrors[`${row.code}.callback_base_url`] ? (
                        <p className="text-xs text-destructive">{fieldErrors[`${row.code}.callback_base_url`]}</p>
                      ) : null}
                    </div>
                  </div>

                  <div className="flex justify-end">
                    <Button type="button" variant="outline" disabled={testing === row.code} onClick={() => void test(row.code)}>
                      {testing === row.code ? tNav('common.loading') : t('testConnection')}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
          <Button type="button" disabled={saving} onClick={() => void save()}>
            {saving ? tNav('common.loading') : t('save')}
          </Button>
        </div>
      )}
    </CrmPageLayout>
  );
}
