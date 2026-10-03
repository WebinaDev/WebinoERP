'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale-next';
import { ActivityTimeline } from '@/features/modules/crm/ActivityTimeline';
import { dashboardHref } from '@/lib/route-resolver';
import {
  deployResource,
  fetchCrmSites,
  startResource,
  stopResource,
  type PlatformResource,
} from '@/lib/api/platform';

type Props = { id: string };

export function CustomerDetailPage({ id }: Props) {
  const t = useTranslations('crm.customer360');
  const tC = useTranslations('crm.customers');
  const tPlatform = useTranslations('platform.crmSites');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { formatDate } = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [account, setAccount] = useState<Record<string, unknown> | null>(null);
  const [deals, setDeals] = useState<Record<string, unknown>[]>([]);
  const [tickets, setTickets] = useState<Record<string, unknown>[]>([]);
  const [contacts, setContacts] = useState<Record<string, unknown>[]>([]);
  const [platformSites, setPlatformSites] = useState<PlatformResource[]>([]);
  const [platformProvisions, setPlatformProvisions] = useState<Record<string, unknown>[]>([]);
  const [projects, setProjects] = useState<Record<string, unknown>[]>([]);
  const [portalEmail, setPortalEmail] = useState('');
  const [portalName, setPortalName] = useState('');
  const [portalPassword, setPortalPassword] = useState<string | null>(null);
  const [portalBusy, setPortalBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [focusDeal, setFocusDeal] = useState<string | null>(null);
  const [duplicateId, setDuplicateId] = useState('');
  const [fieldValues, setFieldValues] = useState<{ key: string; label: string; value: string }[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [accRes, dealsRes, ticketsRes, contactsRes, projectsRes, sitesRes] = await Promise.all([
        apiClient.get(`/v1/crm/accounts/${id}`),
        apiClient.get('/v1/crm/deals', { params: { account_id: id, per_page: 20 } }),
        apiClient.get('/v1/projects/tickets', { params: { account_id: id, per_page: 20 } }),
        apiClient.get('/v1/crm/contacts', { params: { account_id: id, per_page: 20 } }),
        apiClient.get('/v1/projects/projects', { params: { customer_account_id: id, per_page: 20 } }),
        fetchCrmSites(id).catch(() => ({ resources: [], provisions: [] })),
      ]);
      setAccount(unwrapData(accRes) as Record<string, unknown>);
      const dealsBody = dealsRes.data as { data?: unknown[] };
      const ticketsBody = ticketsRes.data as { data?: unknown[] };
      const contactsBody = contactsRes.data as { data?: unknown[] };
      const projectsBody = projectsRes.data as { data?: unknown[] };
      setDeals(Array.isArray(dealsBody.data) ? (dealsBody.data as Record<string, unknown>[]) : []);
      setTickets(Array.isArray(ticketsBody.data) ? (ticketsBody.data as Record<string, unknown>[]) : []);
      setContacts(Array.isArray(contactsBody.data) ? (contactsBody.data as Record<string, unknown>[]) : []);
      setProjects(Array.isArray(projectsBody.data) ? (projectsBody.data as Record<string, unknown>[]) : []);
      setPlatformSites(sitesRes.resources ?? []);
      setPlatformProvisions(sitesRes.provisions ?? []);
      const fieldsRes = await apiClient.get('/v1/crm/custom-field-values', { params: { entity_type: 'account', entity_id: id } }).catch(() => null);
      const fieldRows = fieldsRes ? unwrapData<{ field: { key: string; label: string }; value: string | null }[]>(fieldsRes) : [];
      setFieldValues((fieldRows ?? []).map((row) => ({ key: row.field.key, label: row.field.label, value: row.value ?? '' })));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  async function grantPortal() {
    if (!portalEmail.trim() || !portalName.trim()) return;
    setPortalBusy(true);
    setPortalPassword(null);
    try {
      const res = await apiClient.post(`/v1/crm/accounts/${id}/portal-access`, {
        email: portalEmail.trim(),
        name: portalName.trim(),
      });
      const body = unwrapData<{ temporary_password?: string | null }>(res);
      setPortalPassword(body?.temporary_password ?? null);
      setPortalEmail('');
      setPortalName('');
      await load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setPortalBusy(false);
    }
  }

  function contactLabel(c: Record<string, unknown>): string {
    const joined = [c.first_name, c.last_name].filter(Boolean).join(' ');
    return joined || String(c.name ?? c.email ?? c.id);
  }

  async function handlePlatformAction(resourceId: number, action: 'start' | 'stop' | 'deploy') {
    try {
      if (action === 'start') await startResource(resourceId);
      else if (action === 'stop') await stopResource(resourceId);
      else await deployResource(resourceId);
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  return (
    <CrmPageLayout
      title={String(account?.name ?? account?.company_name ?? tC('title'))}
      actions={
        <Button variant="outline" size="sm" asChild>
          <Link href={dashboardHref(locale, 'crm/customers')}>{tNav('common.back')}</Link>
        </Button>
      }
      {...layoutProps}
    >
      {loading ? (
        <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
      ) : account ? (
        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-lg">{t('title')}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex flex-wrap gap-2">
                <Badge variant="secondary">{String(account.type ?? account.status ?? tNav('common.none'))}</Badge>
                {account.website ? <Badge variant="outline">{String(account.website)}</Badge> : null}
              </div>
              <p><span className="text-muted-foreground">{tC('email')}: </span>{String(account.email ?? tNav('common.none'))}</p>
              <p><span className="text-muted-foreground">{tC('phone')}: </span>{String(account.phone ?? account.mobile ?? tNav('common.none'))}</p>
              {account.description ? <p className="text-muted-foreground">{String(account.description)}</p> : null}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-lg">{t('portalTitle')}</CardTitle></CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="grid gap-2 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label htmlFor="portal-name">{t('portalName')}</Label>
                  <Input id="portal-name" value={portalName} onChange={(e) => setPortalName(e.target.value)} />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="portal-email">{t('portalEmail')}</Label>
                  <Input id="portal-email" type="email" value={portalEmail} onChange={(e) => setPortalEmail(e.target.value)} dir="ltr" />
                </div>
              </div>
              <Button type="button" size="sm" disabled={portalBusy} onClick={() => void grantPortal()}>
                {t('portalGrant')}
              </Button>
              {portalPassword ? (
                <p className="rounded-md border bg-muted/40 p-2 text-xs" dir="ltr">
                  {t('portalPassword')}: {portalPassword}
                </p>
              ) : null}
            </CardContent>
          </Card>
          <Separator />
          <Card>
            <CardHeader><CardTitle className="text-lg">{tNav('suite.merge')}</CardTitle></CardHeader>
            <CardContent className="flex flex-col gap-2 sm:flex-row">
              <Input inputMode="numeric" value={duplicateId} onChange={(e) => setDuplicateId(e.target.value)} placeholder={tNav('suite.duplicateId')} aria-label={tNav('suite.duplicateId')} />
              <Button type="button" size="sm" variant="outline" disabled={!duplicateId} onClick={() => void apiClient.post('/v1/crm/accounts/merge', { primary_id: Number(id), duplicate_id: Number(duplicateId) }).then(() => { setDuplicateId(''); return load(); }).catch(applyAxiosError)}>
                {tNav('suite.mergeAction')}
              </Button>
            </CardContent>
          </Card>
          {fieldValues.length > 0 ? (
            <Card>
              <CardHeader><CardTitle className="text-lg">{tNav('suite.fields')}</CardTitle></CardHeader>
              <CardContent className="grid gap-2 sm:grid-cols-2">
                {fieldValues.map((field) => (
                  <div key={field.key} className="space-y-1">
                    <Label htmlFor={`cf-${field.key}`}>{field.label}</Label>
                    <Input id={`cf-${field.key}`} value={field.value} onChange={(e) => setFieldValues((rows) => rows.map((row) => row.key === field.key ? { ...row, value: e.target.value } : row))} onBlur={() => void apiClient.put('/v1/crm/custom-field-values', { entity_type: 'account', entity_id: Number(id), values: { [field.key]: field.value } }).catch(applyAxiosError)} />
                  </div>
                ))}
              </CardContent>
            </Card>
          ) : null}
          <Tabs defaultValue="timeline">
            <TabsList className="flex h-auto w-full flex-wrap justify-start">
              <TabsTrigger value="timeline">{tNav('suite.timeline')}</TabsTrigger>
              <TabsTrigger value="deals">{t('deals')}</TabsTrigger>
              <TabsTrigger value="tickets">{t('tickets')}</TabsTrigger>
              <TabsTrigger value="contacts">{t('contacts')}</TabsTrigger>
              <TabsTrigger value="projects">{t('projects')}</TabsTrigger>
              <TabsTrigger value="platform">{tPlatform('title')}</TabsTrigger>
            </TabsList>
            <TabsContent value="timeline">
              <ActivityTimeline accountId={id} />
            </TabsContent>
            <TabsContent value="deals" className="space-y-2">
              {deals.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                deals.map((d) => (
                  <div key={String(d.id)} className="rounded-md border p-3 text-sm">
                    <div className="flex items-center justify-between gap-2">
                      <p className="font-medium">{String(d.title ?? d.name ?? d.id)}</p>
                      <Button type="button" size="sm" variant="ghost" onClick={() => setFocusDeal(String(d.id))}>{tNav('suite.timeline')}</Button>
                    </div>
                    <p className="text-muted-foreground">{String((d.stage as { name?: string } | undefined)?.name ?? d.status ?? tNav('common.none'))}</p>
                  </div>
                ))
              )}
              {focusDeal ? <ActivityTimeline dealId={focusDeal} /> : null}
            </TabsContent>
            <TabsContent value="tickets" className="space-y-2">
              {tickets.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                tickets.map((tk) => (
                  <div key={String(tk.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{String(tk.subject ?? tk.title ?? tk.id)}</p>
                    <p className="text-muted-foreground">{String(tk.status ?? tNav('common.none'))}</p>
                  </div>
                ))
              )}
            </TabsContent>
            <TabsContent value="contacts" className="space-y-2">
              {contacts.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                contacts.map((c) => (
                  <div key={String(c.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{contactLabel(c)}</p>
                    <p className="text-muted-foreground">{String(c.email ?? c.phone ?? tNav('common.none'))}</p>
                  </div>
                ))
              )}
            </TabsContent>
            <TabsContent value="projects" className="space-y-2">
              {projects.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                projects.map((p) => (
                  <div key={String(p.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{String(p.name ?? p.id)}</p>
                    <p className="text-muted-foreground">
                      {String(p.status ?? tNav('common.none'))} · {Number(p.progress_percent ?? 0)}%
                    </p>
                  </div>
                ))
              )}
            </TabsContent>
            <TabsContent value="platform" className="space-y-3">
              {platformSites.length === 0 && platformProvisions.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : null}
              {platformSites.map((site) => (
                <div key={site.id} className="rounded-md border p-3 text-sm space-y-2">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="font-medium">{site.name}</p>
                      <p className="font-mono text-xs text-muted-foreground">{site.fqdn ?? site.domains?.[0]?.domain ?? tNav('common.none')}</p>
                    </div>
                    <Badge variant="secondary">{site.status ?? tNav('common.none')}</Badge>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" asChild>
                      <Link href={dashboardHref(locale, `admin/platform/resources/${site.id}`)}>{tPlatform('manage')}</Link>
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => void handlePlatformAction(site.id, 'start')}>{tPlatform('start')}</Button>
                    <Button size="sm" variant="outline" onClick={() => void handlePlatformAction(site.id, 'stop')}>{tPlatform('stop')}</Button>
                    <Button size="sm" variant="secondary" onClick={() => void handlePlatformAction(site.id, 'deploy')}>{tPlatform('redeploy')}</Button>
                  </div>
                </div>
              ))}
              {platformProvisions.map((p) => (
                <div key={String(p.id)} className="rounded-md border p-3 text-sm">
                  <p className="font-medium">{String(p.domain ?? p.slug ?? p.id)}</p>
                  <p className="text-muted-foreground">{String(p.status ?? tNav('common.none'))}</p>
                </div>
              ))}
            </TabsContent>
          </Tabs>
          {account.created_at ? (
            <p className="text-xs text-muted-foreground">
              {tNav('common.created')}: {formatDate(String(account.created_at))}
            </p>
          ) : null}
        </div>
      ) : null}
    </CrmPageLayout>
  );
}
