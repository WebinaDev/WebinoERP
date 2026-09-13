'use client';

import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Loader2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { adminModirPayamakSend } from '@/lib/api/modirpayamak';
import { edgeField, type EdgeRow } from '@/lib/api/modirpayamak-edge';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakLineSelect } from './components/line-select';
import { ModirPayamakPatternSelect } from './components/pattern-select';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakSendPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const searchParams = useSearchParams();
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();

  const initialMode = searchParams.get('mode') === 'pattern' ? 'pattern' : 'simple';
  const [mode, setMode] = useState<'simple' | 'pattern'>(initialMode);
  const [from, setFrom] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState(searchParams.get('message') ?? '');
  const [patternCode, setPatternCode] = useState(searchParams.get('pattern') ?? '');
  const [patternParams, setPatternParams] = useState<Record<string, string>>({});
  const [debitTenant, setDebitTenant] = useState(false);
  const [domain, setDomain] = useState('');
  const [loading, setLoading] = useState(false);
  const [lastResult, setLastResult] = useState<{ id: string; cost: string; status: string } | null>(null);

  useEffect(() => {
    const msg = searchParams.get('message');
    if (msg) setMessage(msg);
    const pat = searchParams.get('pattern');
    if (pat) {
      setPatternCode(pat);
      setMode('pattern');
    }
    if (searchParams.get('mode') === 'simple') setMode('simple');
  }, [searchParams]);

  const recipientCount = useMemo(
    () => phone.split(/[\s,;\n]+/).filter(Boolean).length,
    [phone],
  );

  const onPatternSelect = (row: EdgeRow | null) => {
    if (!row) {
      setPatternParams({});
      return;
    }
    const body = edgeField(row, 'pattern_message', 'message', 'text', 'body');
    const vars = [...new Set((body.match(/%([a-zA-Z0-9_]+)%/g) ?? []).map((m) => m.slice(1, -1)))];
    const next: Record<string, string> = {};
    for (const key of vars) next[key] = '';
    setPatternParams(next);
  };

  const send = async () => {
    setLoading(true);
    setError(null);
    setSuccess(null);
    setLastResult(null);
    const recipients = phone.split(/[\s,;\n]+/).filter(Boolean);
    const base: Record<string, unknown> = {
      from_number: from,
      recipients,
    };
    if (debitTenant) {
      base.debit_tenant = true;
      if (domain.trim()) base.domain = domain.trim();
    }
    const payload =
      mode === 'pattern'
        ? { ...base, sending_type: 'pattern', code: patternCode, params: patternParams }
        : { ...base, sending_type: 'webservice', message };
    try {
      const data = (await adminModirPayamakSend(payload)) as Record<string, unknown> | undefined;
      const edge =
        data && typeof data.edge === 'object' && data.edge && !Array.isArray(data.edge)
          ? (data.edge as Record<string, unknown>)
          : data;
      setLastResult({
        id: edge ? String(edge.message_id ?? edge.id ?? '—') : '—',
        cost: edge ? String(edge.cost ?? edge.price ?? '—') : '—',
        status: edge ? String(edge.status ?? 'sent') : 'sent',
      });
      setSuccess(t('sendSuccess'));
    } catch (e) {
      setError(getAxiosMessage(e) || t('sendFailed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpSend')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpSend')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {configLoading ? (
        <Card className="max-w-2xl">
          <CardContent className="h-64 animate-pulse bg-muted/30" />
        </Card>
      ) : configured ? (
        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>{tNav('nav.erp.admin.mpSend')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant={mode === 'simple' ? 'default' : 'outline'}
                onClick={() => setMode('simple')}
              >
                {t('simpleSend')}
              </Button>
              <Button
                type="button"
                variant={mode === 'pattern' ? 'default' : 'outline'}
                onClick={() => setMode('pattern')}
              >
                {t('patternSend')}
              </Button>
            </div>
            <div className="space-y-2">
              <Label>{t('fromNumber')}</Label>
              <ModirPayamakLineSelect value={from} onChange={setFrom} disabled={loading} />
            </div>
            <div className="space-y-2">
              <Label>{t('recipients')}</Label>
              <Textarea
                dir="ltr"
                rows={3}
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+98912..."
              />
              <p className="text-xs text-muted-foreground">
                {t('recipientCount', { count: recipientCount })}
              </p>
            </div>
            {mode === 'pattern' ? (
              <>
                <div className="space-y-2">
                  <Label>{t('patternCode')}</Label>
                  <ModirPayamakPatternSelect
                    value={patternCode}
                    onChange={setPatternCode}
                    onSelectRow={onPatternSelect}
                    disabled={loading}
                  />
                </div>
                {Object.keys(patternParams).length === 0 ? (
                  <p className="text-xs text-muted-foreground">{t('noPatternVars')}</p>
                ) : (
                  Object.entries(patternParams).map(([key, val]) => (
                    <div key={key} className="space-y-2">
                      <Label className="font-mono text-xs" dir="ltr">
                        %{key}%
                      </Label>
                      <Input
                        value={val}
                        onChange={(e) => setPatternParams((p) => ({ ...p, [key]: e.target.value }))}
                      />
                    </div>
                  ))
                )}
              </>
            ) : (
              <div className="space-y-2">
                <Label>{t('message')}</Label>
                <Textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={4} />
              </div>
            )}
            <div className="flex items-center gap-2">
              <Checkbox
                id="debit-tenant"
                checked={debitTenant}
                onCheckedChange={(v) => setDebitTenant(v === true)}
              />
              <Label htmlFor="debit-tenant">{t('debitTenantWallet')}</Label>
            </div>
            {debitTenant ? (
              <div className="space-y-2">
                <Label>{t('domain')}</Label>
                <Input
                  dir="ltr"
                  value={domain}
                  onChange={(e) => setDomain(e.target.value)}
                  placeholder="example.com"
                />
              </div>
            ) : null}
            <Button
              type="button"
              onClick={() => void send()}
              disabled={loading || !from || recipientCount === 0}
            >
              {loading ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
              {t('send')}
            </Button>
            {lastResult ? (
              <Card className="border-green-500/30 bg-green-500/5">
                <CardContent className="space-y-2 pt-4 text-sm">
                  <p className="font-medium">{t('sendSuccess')}</p>
                  <div className="flex flex-wrap gap-4">
                    <span>
                      {t('messageId')}: {lastResult.id}
                    </span>
                    <span>
                      {t('cost')}: {lastResult.cost}
                    </span>
                    <ModirPayamakStatusBadge status={lastResult.status} />
                  </div>
                </CardContent>
              </Card>
            ) : null}
          </CardContent>
        </Card>
      ) : null}
    </CrmPageLayout>
  );
}
