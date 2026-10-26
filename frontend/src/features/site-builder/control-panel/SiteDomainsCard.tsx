'use client';

import { useCallback, useEffect, useState } from 'react';
import { useLocale } from 'next-intl';
import { Globe, Loader2, Pencil, Plus, RefreshCw, Trash2, X, Check } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

export type SiteDomainRole = 'primary' | 'alias' | 'redirect';
export type SiteDomain = {
  id: number;
  domain: string;
  role: SiteDomainRole;
  include_www: boolean;
  redirect_path: string | null;
  redirect_status: number;
  preserve_path: boolean;
};
type Draft = Omit<SiteDomain, 'id' | 'role'> & { role: Exclude<SiteDomainRole, 'primary'> | 'primary' };

const L = {
  fa: {
    title: 'دامنه‌ها',
    description: 'دامنه اصلی، دامنه‌های هم‌ارز و دامنه‌هایی که به سایت ریدایرکت می‌شوند.',
    domain: 'دامنه',
    role: 'نقش',
    primary: 'اصلی',
    alias: 'هم‌ارز',
    redirect: 'ریدایرکت',
    www: 'نسخه www هم پوشش داده شود',
    target: 'مسیر مقصد',
    targetHint: 'مثلا /fa برای نسخه فارسی',
    status: 'کد ریدایرکت',
    keepPath: 'مسیر درخواست حفظ شود',
    add: 'افزودن دامنه',
    save: 'ذخیره',
    cancel: 'انصراف',
    edit: 'ویرایش',
    remove: 'حذف',
    confirmRemove: 'این دامنه حذف شود؟',
    empty: 'هنوز دامنه‌ای ثبت نشده است.',
    goesTo: 'به',
    dns: 'رکورد A هر دامنه (و www) باید به همین سرور اشاره کند.',
    applied: 'تنظیمات وب‌سرور به‌روز شد.',
    notApplied: 'ذخیره شد. تنظیمات وب‌سرور اعمال نشد:',
    primaryNote: 'تغییر دامنه اصلی از بخش تغییر دامنه انجام می‌شود.',
    refresh: 'بارگذاری دوباره',
  },
  en: {
    title: 'Domains',
    description: 'Primary domain, aliases and domains that redirect to this site.',
    domain: 'Domain',
    role: 'Role',
    primary: 'Primary',
    alias: 'Alias',
    redirect: 'Redirect',
    www: 'Also cover www',
    target: 'Target path',
    targetHint: 'e.g. /fa for the Persian version',
    status: 'Redirect code',
    keepPath: 'Keep request path',
    add: 'Add domain',
    save: 'Save',
    cancel: 'Cancel',
    edit: 'Edit',
    remove: 'Remove',
    confirmRemove: 'Remove this domain?',
    empty: 'No domains yet.',
    goesTo: 'to',
    dns: 'Each domain (and www) needs an A record pointing at this server.',
    applied: 'Web server config updated.',
    notApplied: 'Saved. Web server config not applied:',
    primaryNote: 'Change the primary domain from the change domain section.',
    refresh: 'Refresh',
  },
};

const EMPTY: Draft = { domain: '', role: 'alias', include_www: true, redirect_path: '/', redirect_status: 301, preserve_path: true };
const base = (id: number) => `/v1/site-builder/provisions/${id}/domains`;

type Resp = { data: SiteDomain[]; meta?: { caddy?: { ok: boolean; applied: boolean; log: string } } };

export function SiteDomainsCard({ provisionId }: { provisionId: number }) {
  const locale = useLocale();
  const t = locale.startsWith('fa') ? L.fa : L.en;
  const [rows, setRows] = useState<SiteDomain[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [draft, setDraft] = useState<Draft>(EMPTY);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(base(provisionId));
      setRows((res.data as Resp).data ?? []);
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e) || 'Error');
    } finally {
      setLoading(false);
    }
  }, [provisionId]);

  useEffect(() => {
    void load();
  }, [load]);

  const after = (r: Resp) => {
    setRows(r.data ?? []);
    const c = r.meta?.caddy;
    setNotice(c ? (c.ok && c.applied ? t.applied : c.applied || !c.ok ? `${t.notApplied} ${c.log}` : null) : null);
    setEditing(null);
    setError(null);
  };

  const submit = async () => {
    setBusy(true);
    try {
      const body = { ...draft, redirect_path: draft.role === 'redirect' ? draft.redirect_path : null };
      const res =
        editing === 'new'
          ? await apiClient.post(base(provisionId), body)
          : await apiClient.patch(`${base(provisionId)}/${editing}`, body);
      after(res.data as Resp);
    } catch (e) {
      setError(getAxiosMessage(e) || 'Error');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (row: SiteDomain) => {
    if (!window.confirm(t.confirmRemove)) return;
    setBusy(true);
    try {
      after((await apiClient.delete(`${base(provisionId)}/${row.id}`)).data as Resp);
    } catch (e) {
      setError(getAxiosMessage(e) || 'Error');
    } finally {
      setBusy(false);
    }
  };

  const primary = rows.find((r) => r.role === 'primary')?.domain;
  const isPrimaryEdit = editing !== null && editing !== 'new' && rows.find((r) => r.id === editing)?.role === 'primary';

  const form = (
    <div className="bg-muted/30 space-y-3 rounded-xl border p-4" data-testid="site-domain-form">
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="space-y-1 text-xs">
          <span className="text-muted-foreground">{t.domain}</span>
          <Input
            dir="ltr"
            className="text-start"
            value={draft.domain}
            disabled={isPrimaryEdit}
            placeholder="example.ir"
            onChange={(e) => setDraft({ ...draft, domain: e.target.value })}
            data-testid="site-domain-input"
          />
        </label>
        <label className="space-y-1 text-xs">
          <span className="text-muted-foreground">{t.role}</span>
          <select
            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
            value={draft.role}
            disabled={isPrimaryEdit}
            onChange={(e) => setDraft({ ...draft, role: e.target.value as Draft['role'] })}
            data-testid="site-domain-role"
          >
            {isPrimaryEdit ? <option value="primary">{t.primary}</option> : null}
            <option value="alias">{t.alias}</option>
            <option value="redirect">{t.redirect}</option>
          </select>
        </label>
        {draft.role === 'redirect' ? (
          <>
            <label className="space-y-1 text-xs">
              <span className="text-muted-foreground">{t.target}</span>
              <Input
                dir="ltr"
                className="text-start"
                value={draft.redirect_path ?? ''}
                placeholder="/fa"
                onChange={(e) => setDraft({ ...draft, redirect_path: e.target.value })}
              />
              <span className="text-muted-foreground block">{t.targetHint}</span>
            </label>
            <label className="space-y-1 text-xs">
              <span className="text-muted-foreground">{t.status}</span>
              <select
                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                value={draft.redirect_status}
                onChange={(e) => setDraft({ ...draft, redirect_status: Number(e.target.value) })}
              >
                {[301, 302, 307, 308].map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </label>
          </>
        ) : null}
      </div>
      <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        <label className="flex items-center gap-2">
          <Switch checked={draft.include_www} onCheckedChange={(v) => setDraft({ ...draft, include_www: v })} />
          {t.www}
        </label>
        {draft.role === 'redirect' ? (
          <label className="flex items-center gap-2">
            <Switch checked={draft.preserve_path} onCheckedChange={(v) => setDraft({ ...draft, preserve_path: v })} />
            {t.keepPath}
          </label>
        ) : null}
      </div>
      {isPrimaryEdit ? <p className="text-muted-foreground text-xs">{t.primaryNote}</p> : null}
      <div className="flex flex-wrap gap-2">
        <Button type="button" size="sm" className="gap-1.5" disabled={busy || !draft.domain.trim()} onClick={() => void submit()} data-testid="site-domain-save">
          {busy ? <Loader2 className="size-4 animate-spin" /> : <Check className="size-4" />}
          {t.save}
        </Button>
        <Button type="button" size="sm" variant="ghost" className="gap-1.5" onClick={() => setEditing(null)}>
          <X className="size-4" />
          {t.cancel}
        </Button>
      </div>
    </div>
  );

  return (
    <Card variant="glass" className="border-border/70 overflow-hidden" data-testid="site-domains" dir={locale.startsWith('fa') ? 'rtl' : 'ltr'}>
      <CardHeader className="border-b bg-muted/30 pb-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3">
            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
              <Globe className="size-5" />
            </div>
            <div>
              <CardTitle className="text-lg">{t.title}</CardTitle>
              <CardDescription className="mt-1">{t.description}</CardDescription>
            </div>
          </div>
          <Button type="button" variant="ghost" size="sm" onClick={() => void load()} disabled={loading} aria-label={t.refresh}>
            {loading ? <Loader2 className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-3 pt-5 text-sm">
        {error ? <p className="text-destructive" role="alert">{error}</p> : null}
        {notice ? <p className="text-muted-foreground text-xs">{notice}</p> : null}
        {!loading && rows.length === 0 ? <p className="text-muted-foreground text-xs">{t.empty}</p> : null}
        <ul className="space-y-2">
          {rows.map((r) =>
            editing === r.id ? (
              <li key={r.id}>{form}</li>
            ) : (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3" data-testid="site-domain-row">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                  <Badge variant={r.role === 'primary' ? 'default' : r.role === 'alias' ? 'secondary' : 'outline'}>{t[r.role]}</Badge>
                  <span dir="ltr" className="truncate font-medium">{r.domain}</span>
                  {r.include_www ? <span className="text-muted-foreground text-xs" dir="ltr">+www</span> : null}
                  {r.role === 'redirect' && primary ? (
                    <span className="text-muted-foreground text-xs">
                      {t.goesTo} <span dir="ltr">{primary}{r.redirect_path && r.redirect_path !== '/' ? r.redirect_path : ''}</span> ({r.redirect_status})
                    </span>
                  ) : null}
                </div>
                <div className="flex gap-1">
                  <Button type="button" variant="ghost" size="sm" aria-label={t.edit} onClick={() => { setDraft({ ...EMPTY, ...r, redirect_path: r.redirect_path ?? '/' }); setEditing(r.id); }}>
                    <Pencil className="size-4" />
                  </Button>
                  {r.role !== 'primary' ? (
                    <Button type="button" variant="ghost" size="sm" aria-label={t.remove} disabled={busy} onClick={() => void remove(r)}>
                      <Trash2 className="text-destructive size-4" />
                    </Button>
                  ) : null}
                </div>
              </li>
            ),
          )}
        </ul>
        {editing === 'new' ? form : (
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => { setDraft(EMPTY); setEditing('new'); }} data-testid="site-domain-add">
            <Plus className="size-4" />
            {t.add}
          </Button>
        )}
        <p className="text-muted-foreground text-xs">{t.dns}</p>
      </CardContent>
    </Card>
  );
}
