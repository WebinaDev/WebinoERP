'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  deleteModirPayamakDomainSecretary,
  getModirPayamakCustomers,
  getModirPayamakDomainSecretaries,
  saveModirPayamakDomainSecretary,
} from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakBreadcrumb } from './components/shared';

const TYPES = ['auto_reply', 'inbox_forward', 'code_reader', 'membership'] as const;

export function ModirpayamakSecretariesPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const [domain, setDomain] = useState('');
  const [domains, setDomains] = useState<string[]>([]);
  const [rows, setRows] = useState<Array<Record<string, unknown>>>([]);
  const [loading, setLoading] = useState(false);
  const [name, setName] = useState('');
  const [type, setType] = useState<(typeof TYPES)[number]>('auto_reply');
  const [keywords, setKeywords] = useState('*');
  const [replyBody, setReplyBody] = useState('');
  const [forwardTo, setForwardTo] = useState('');

  const loadDomains = async () => {
    try {
      const accounts = await getModirPayamakCustomers();
      setDomains(accounts.map((a) => a.domain).filter(Boolean));
    } catch {
      /* ignore */
    }
  };

  const load = async (d: string) => {
    if (!d.trim()) return;
    setLoading(true);
    setError(null);
    try {
      const data = await getModirPayamakDomainSecretaries(d.trim());
      setRows(Array.isArray(data.secretaries) ? data.secretaries : []);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  };

  const remove = async (id: number) => {
    try {
      await deleteModirPayamakDomainSecretary(domain, id);
      setSuccess(tCommon('deleted'));
      void load(domain);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const addRule = async () => {
    if (!domain.trim()) {
      setError(t('domainRequired'));
      return;
    }
    if (!name.trim()) {
      setError(t('secretaryNameRequired'));
      return;
    }
    try {
      await saveModirPayamakDomainSecretary({
        domain: domain.trim(),
        type,
        name: name.trim(),
        keywords: keywords.trim() || '*',
        reply_body: replyBody,
        forward_to: forwardTo.trim() || undefined,
        enabled: true,
      });
      setSuccess(tCommon('saved'));
      setName('');
      setReplyBody('');
      setForwardTo('');
      setKeywords('*');
      void load(domain);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const typeLabel = (key: string) => {
    try {
      return t(`secretaryTypes.${key}` as 'secretaryTypes.auto_reply');
    } catch {
      return key;
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpSecretaries')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpSecretaries')} />
      <div className="mb-4 flex flex-wrap items-end gap-2">
        <div>
          <Label>{t('domain')}</Label>
          <Input
            className="mt-1 min-w-[220px]"
            list="mp-sec-domains"
            value={domain}
            onFocus={() => void loadDomains()}
            onChange={(e) => setDomain(e.target.value)}
          />
          <datalist id="mp-sec-domains">
            {domains.map((d) => (
              <option key={d} value={d} />
            ))}
          </datalist>
        </div>
        <Button type="button" disabled={loading} onClick={() => void load(domain)}>
          {tCommon('refresh')}
        </Button>
      </div>

      <div className="mb-6 grid max-w-2xl gap-3 rounded-lg border p-4">
        <p className="text-sm font-medium">{t('newSecretary')}</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <Label>{t('secretaryName')}</Label>
            <Input className="mt-1" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div>
            <Label>{t('secretaryType')}</Label>
            <Select value={type} onValueChange={(v) => setType(v as (typeof TYPES)[number])}>
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TYPES.map((key) => (
                  <SelectItem key={key} value={key}>
                    {typeLabel(key)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div>
          <Label>{t('keywords')}</Label>
          <Input className="mt-1" value={keywords} onChange={(e) => setKeywords(e.target.value)} />
        </div>
        <div>
          <Label>{t('replyBody')}</Label>
          <Textarea className="mt-1" rows={3} value={replyBody} onChange={(e) => setReplyBody(e.target.value)} />
        </div>
        <div>
          <Label>{t('forwardTo')}</Label>
          <Input
            className="mt-1 font-mono"
            dir="ltr"
            value={forwardTo}
            onChange={(e) => setForwardTo(e.target.value)}
            placeholder="09xxxxxxxxx"
          />
        </div>
        <Button type="button" className="w-fit" onClick={() => void addRule()}>
          {tCommon('add')}
        </Button>
      </div>

      <ul className="divide-y rounded border">
        {rows.map((r) => {
          const id = Number(r.id ?? 0);
          return (
            <li key={id} className="flex items-center justify-between gap-3 p-3 text-sm">
              <div>
                <p className="font-medium">
                  {String(r.name || r.type)} · {typeLabel(String(r.type || ''))}
                </p>
                <p className="font-mono text-xs text-muted-foreground" dir="ltr">
                  {String(r.keywords || '')}
                </p>
                {r.reply_body ? (
                  <p className="mt-1 text-xs text-muted-foreground">{String(r.reply_body)}</p>
                ) : null}
              </div>
              <Button type="button" size="sm" variant="outline" onClick={() => void remove(id)}>
                {tCommon('delete')}
              </Button>
            </li>
          );
        })}
      </ul>
      {!loading && !rows.length ? (
        <p className="mt-4 text-sm text-muted-foreground">{t('secretariesEmpty')}</p>
      ) : null}
    </CrmPageLayout>
  );
}
