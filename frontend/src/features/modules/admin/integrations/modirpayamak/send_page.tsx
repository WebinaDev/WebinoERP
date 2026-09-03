'use client';

import { useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Loader2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { modirPayamakSend } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakTenantDomain } from './hooks/useModirPayamakTenantDomain';

export function ModirpayamakSendPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const searchParams = useSearchParams();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const { domain, setDomain, domains } = useModirPayamakTenantDomain();
  const [mode, setMode] = useState<'simple' | 'pattern'>(searchParams.get('mode') === 'pattern' ? 'pattern' : 'simple');
  const [debitTenant, setDebitTenant] = useState(false);
  const [from, setFrom] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState(searchParams.get('message') ?? '');
  const [patternCode, setPatternCode] = useState(searchParams.get('pattern') ?? '');
  const [loading, setLoading] = useState(false);
  const [lastResult, setLastResult] = useState<Record<string, unknown> | null>(null);

  const recipientCount = useMemo(() => phone.split(/[\s,;\n]+/).filter(Boolean).length, [phone]);

  const send = async () => {
    if (debitTenant && !domain.trim()) {
      setError(t('domainRequired'));
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const recipients = phone.split(/[\s,;\n]+/).filter(Boolean);
      const payload: Record<string, unknown> =
        mode === 'pattern'
          ? { from_number: from, message: patternCode, recipients, debit_tenant: debitTenant }
          : { from_number: from || undefined, message, recipients, debit_tenant: debitTenant };
      if (debitTenant) payload.domain = domain.trim();
      const res = await modirPayamakSend(payload);
      setLastResult(res as Record<string, unknown>);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpSend')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpSend')} />
      {configLoading ? null : !configured ? (
        <ModirPayamakNotConfigured />
      ) : (
        <Card>
          <CardHeader>
            <CardTitle className="flex gap-2">
              <Button variant={mode === 'simple' ? 'default' : 'outline'} size="sm" onClick={() => setMode('simple')}>
                {t('sendSimple')}
              </Button>
              <Button variant={mode === 'pattern' ? 'default' : 'outline'} size="sm" onClick={() => setMode('pattern')}>
                {t('sendPattern')}
              </Button>
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center gap-2">
              <input
                id="mp-debit"
                type="checkbox"
                className="size-4"
                checked={debitTenant}
                onChange={(e) => setDebitTenant(e.target.checked)}
              />
              <Label htmlFor="mp-debit">{t('debitTenantWallet')}</Label>
            </div>
            {debitTenant ? (
              <div className="grid gap-2">
                <Label>{t('domain')}</Label>
                <Input
                  list="mp-send-domains"
                  placeholder={t('domain')}
                  value={domain}
                  onChange={(e) => setDomain(e.target.value)}
                  dir="ltr"
                />
                <datalist id="mp-send-domains">
                  {domains.map((d) => (
                    <option key={d} value={d} />
                  ))}
                </datalist>
              </div>
            ) : null}
            <Input placeholder="From line" value={from} onChange={(e) => setFrom(e.target.value)} dir="ltr" />
            <Textarea placeholder={t('recipients')} value={phone} onChange={(e) => setPhone(e.target.value)} rows={3} />
            <p className="text-muted-foreground text-xs">{recipientCount} recipients</p>
            {mode === 'simple' ? (
              <Textarea placeholder={t('message')} value={message} onChange={(e) => setMessage(e.target.value)} rows={5} />
            ) : (
              <Input placeholder="Pattern code" value={patternCode} onChange={(e) => setPatternCode(e.target.value)} dir="ltr" />
            )}
            <Button onClick={() => void send()} disabled={loading}>
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('send')}
            </Button>
            {lastResult ? (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('lastResult')}</CardTitle>
                </CardHeader>
                <CardContent className="text-sm">
                  <pre>{JSON.stringify(lastResult, null, 2)}</pre>
                </CardContent>
              </Card>
            ) : null}
          </CardContent>
        </Card>
      )}
    </CrmPageLayout>
  );
}
