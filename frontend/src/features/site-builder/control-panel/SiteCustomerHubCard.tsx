'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import {
  AlertTriangle,
  ExternalLink,
  Link2,
  Loader2,
  RefreshCw,
  UserRound,
} from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale as useAppLocale } from '@/hooks/use-locale-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AccountSelect } from '@/features/shared/crm/AccountSelect';

type Row = Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

export type SiteCustomerHub = {
  linked: boolean;
  restricted?: boolean;
  can_link: boolean;
  sections: string[];
  actions: Record<string, boolean>;
  provision: Row;
  site_status: Row;
  customer?: { id: number; name: string };
  header?: Row;
  kpis?: Row;
  health?: Row | null;
  alerts?: Row[];
  identity?: Row;
  licenses?: Row[];
  assets?: Row;
  billing?: Row;
  support?: Row;
  usage?: Row;
  timeline?: Row[];
};

const base = (id: number) => `/v1/site-builder/provisions/${id}/customer-hub`;

export async function fetchSiteCustomerHub(id: number): Promise<SiteCustomerHub> {
  const res = await apiClient.get(base(id));
  return (res.data as { data: SiteCustomerHub }).data;
}

export async function linkSiteCustomer(id: number, accountId: number | null): Promise<SiteCustomerHub> {
  const res = await apiClient.put(`${base(id)}/link`, { crm_account_id: accountId });
  return (res.data as { data: SiteCustomerHub }).data;
}

const TONE: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  active: 'default', verified: 'default', good: 'default', paid: 'default', ready: 'default',
  expiring: 'secondary', fair: 'secondary', pending: 'secondary', coming_soon: 'secondary', open: 'secondary',
  expired: 'destructive', suspended: 'destructive', blocked: 'destructive', at_risk: 'destructive', debtor: 'destructive',
  rejected: 'destructive', overdue: 'destructive', critical: 'destructive',
};

function Block({ title, children, testId }: { title: string; children: ReactNode; testId?: string }) {
  return (
    <div className="space-y-2 rounded-xl border bg-background/40 p-4" data-testid={testId}>
      <h4 className="text-sm font-semibold">{title}</h4>
      {children}
    </div>
  );
}

function Stat({ label, value, tone }: { label: string; value: ReactNode; tone?: 'bad' }) {
  return (
    <div className="rounded-lg border bg-muted/30 p-3">
      <div className="text-muted-foreground text-xs">{label}</div>
      <div className={`mt-1 text-base font-semibold ${tone === 'bad' ? 'text-destructive' : ''}`}>{value ?? '—'}</div>
    </div>
  );
}

function Empty({ text }: { text: string }) {
  return <p className="text-muted-foreground text-xs">{text}</p>;
}

export function SiteCustomerHubCard({ provisionId }: { provisionId: number }) {
  const t = useTranslations('siteBuilder.customerHub');
  const locale = useLocale();
  const { formatDate, formatNumber } = useAppLocale();
  const [data, setData] = useState<SiteCustomerHub | null>(null);
  const [panel, setPanel] = useState<Row | null>(null);
  const [panelBusy, setPanelBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pick, setPick] = useState('');
  const [linking, setLinking] = useState(false);

  const money = (n: unknown) => (n === null || n === undefined ? '—' : `${formatNumber(Number(n))} ${t('currency')}`);
  const date = (v: unknown) => (v ? formatDate(String(v)) : '—');
  const badge = (v: unknown, label?: string) =>
    v ? <Badge variant={TONE[String(v)] ?? 'outline'}>{label ?? (t.has(`st.${v}`) ? t(`st.${v}` as never) : String(v))}</Badge> : '—';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await fetchSiteCustomerHub(provisionId));
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [provisionId, t]);

  const loadPanel = useCallback(
    async (accountId: number, fresh = false) => {
      setPanelBusy(true);
      try {
        const res = await apiClient.get(`/v1/crm/customers/${accountId}/hub/site_panel`, { params: fresh ? { fresh: 1 } : undefined });
        const sites = ((res.data as { data: Row }).data?.sites ?? []) as Row[];
        setPanel(sites.find((s) => Number(s.provision_id) === provisionId) ?? { missing: true });
      } catch {
        setPanel({ ok: false });
      } finally {
        setPanelBusy(false);
      }
    },
    [provisionId],
  );

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (data?.linked && data.customer && data.sections.includes('site_panel')) void loadPanel(data.customer.id);
  }, [data?.linked, data?.customer, data?.sections, loadPanel]);

  const link = async (accountId: number | null) => {
    setLinking(true);
    try {
      setData(await linkSiteCustomer(provisionId, accountId));
      setPanel(null);
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    } finally {
      setLinking(false);
    }
  };

  const hubHref = (id: number, tab?: string) => dashboardHref(locale, `crm/customers/${id}${tab ? `?tab=${tab}` : ''}`);

  return (
    <Card variant="glass" className="border-border/70 overflow-hidden" data-testid="site-customer-hub">
      <CardHeader className="border-b bg-muted/30 pb-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3">
            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
              <UserRound className="size-5" />
            </div>
            <div>
              <CardTitle className="text-lg">{t('title')}</CardTitle>
              <CardDescription className="mt-1">{t('description')}</CardDescription>
            </div>
          </div>
          <Button type="button" variant="ghost" size="sm" onClick={() => void load()} disabled={loading} aria-label={t('refresh')}>
            {loading ? <Loader2 className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-4 pt-5 text-sm">
        {error ? <p className="text-destructive text-sm" role="alert">{error}</p> : null}
        {loading && !data ? <Loader2 className="text-muted-foreground size-5 animate-spin" /> : null}

        {data && !data.linked ? (
          <div className="space-y-3" data-testid="customer-hub-unlinked">
            <p>{t('notLinked')}</p>
            {data.can_link ? (
              <div className="flex flex-wrap items-center gap-2">
                <AccountSelect value={pick} onChange={setPick} placeholder={t('pickCustomer')} className="w-64" />
                <Button type="button" className="gap-1.5" disabled={!pick || linking} onClick={() => void link(Number(pick))} data-testid="customer-hub-link">
                  {linking ? <Loader2 className="size-4 animate-spin" /> : <Link2 className="size-4" />}
                  {t('link')}
                </Button>
              </div>
            ) : (
              <Empty text={t('noLinkPermission')} />
            )}
          </div>
        ) : null}

        {data?.linked && data.restricted && data.customer ? (
          <p>
            {data.customer.name} · <span className="text-muted-foreground">{t('restricted')}</span>
          </p>
        ) : null}

        {data?.linked && !data.restricted && data.customer && data.header ? (
          <>
            {/* identity strip */}
            <div className="flex flex-wrap items-start justify-between gap-3" data-testid="customer-hub-header">
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Link href={hubHref(data.customer.id)} className="text-primary text-base font-semibold hover:underline">
                    {data.header.name}
                  </Link>
                  <span className="text-muted-foreground text-xs">{data.header.code}</span>
                  {badge(data.header.status)}
                  {data.identity ? <span className="text-xs">{t('kyc')}: {badge(data.identity.kyc?.status)}</span> : null}
                </div>
                <div className="text-muted-foreground text-xs">
                  {[data.header.primary_email, data.header.primary_phone].filter(Boolean).join(' · ') || '—'}
                  {data.header.owner ? ` · ${t('accountOwner')}: ${data.header.owner.name}` : ''}
                </div>
              </div>
              {data.health ? (
                <div className="text-center" data-testid="customer-hub-health">
                  <div className="text-2xl font-bold">{formatNumber(data.health.score)}</div>
                  <div className="text-xs">{t('health')} {badge(data.health.grade)}</div>
                  <div className="text-muted-foreground text-xs">{t('churnRisk')}: {formatNumber(data.health.churn_risk)}٪</div>
                </div>
              ) : null}
            </div>

            {data.alerts && data.alerts.length > 0 ? (
              <ul className="space-y-1" data-testid="customer-hub-alerts">
                {data.alerts.slice(0, 6).map((a, i) => (
                  <li key={i} className={`flex items-center gap-2 text-xs ${a.severity === 'critical' ? 'text-destructive' : 'text-amber-600'}`}>
                    <AlertTriangle className="size-3.5" />
                    {a.body}
                    {a.value !== undefined && a.value !== null ? ` — ${typeof a.value === 'number' ? formatNumber(a.value) : a.value}` : ''}
                    {a.days !== undefined ? ` (${t('daysLeft', { n: a.days })})` : ''}
                  </li>
                ))}
              </ul>
            ) : null}

            {/* KPIs */}
            <div className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6" data-testid="customer-hub-kpis">
              <Stat label={t('siteStatus')} value={badge(data.site_status?.status)} />
              <Stat label={t('activeLicenses')} value={formatNumber(data.kpis?.active_licenses ?? 0)} />
              <Stat label={t('openTickets')} value={formatNumber(data.kpis?.open_tickets ?? 0)} tone={data.kpis?.open_tickets ? 'bad' : undefined} />
              <Stat label={t('activeSites')} value={formatNumber(data.kpis?.active_sites ?? 0)} />
              {data.billing ? (
                <>
                  <Stat label={t('outstanding')} value={money(data.billing.outstanding)} tone={data.billing.overdue > 0 ? 'bad' : undefined} />
                  <Stat label={t('wallet')} value={money(data.billing.wallet_credit)} />
                </>
              ) : null}
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
              {data.identity ? (
                <Block title={t('identity')} testId="customer-hub-identity">
                  <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    <dt className="text-muted-foreground">{t('kind')}</dt><dd>{data.identity.profile?.customer_kind || data.identity.profile?.type || '—'}</dd>
                    <dt className="text-muted-foreground">{t('nationalId')}</dt><dd>{data.identity.profile?.national_id || '—'}</dd>
                    <dt className="text-muted-foreground">{t('economicCode')}</dt><dd>{data.identity.profile?.economic_code || '—'}</dd>
                    <dt className="text-muted-foreground">{t('city')}</dt><dd>{[data.identity.profile?.province, data.identity.profile?.city].filter(Boolean).join('، ') || '—'}</dd>
                    <dt className="text-muted-foreground">{t('kycVerifiedAt')}</dt><dd>{date(data.identity.kyc?.verified_at)}</dd>
                  </dl>
                  {(data.identity.contacts ?? []).map((c: Row) => (
                    <div key={c.id} className="text-xs">
                      {c.name || '—'} {c.is_default ? <Badge variant="outline">{t('primary')}</Badge> : null}
                      <span className="text-muted-foreground"> {[c.mobile || c.phone, c.email].filter(Boolean).join(' · ')}</span>
                    </div>
                  ))}
                </Block>
              ) : null}

              {data.licenses ? (
                <Block title={t('licenses')} testId="customer-hub-licenses">
                  {data.licenses.length === 0 ? <Empty text={t('none')} /> : (
                    <ul className="space-y-1 text-xs">
                      {data.licenses.map((l) => (
                        <li key={l.id} className="flex flex-wrap items-center justify-between gap-2">
                          <span>
                            {l.domain || l.product} {l.this_site ? <Badge variant="outline">{t('thisSite')}</Badge> : null}
                            <span className="text-muted-foreground"> {l.key_masked}</span>
                          </span>
                          <span className="flex items-center gap-2">
                            {t('expires')}: {date(l.expires_at)}
                            {l.days_left !== null && l.days_left !== undefined ? ` (${t('daysLeft', { n: l.days_left })})` : ''}
                            {badge(l.effective_status)}
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                </Block>
              ) : null}

              {data.assets ? (
                <Block title={t('assets')} testId="customer-hub-assets">
                  <ul className="space-y-1 text-xs">
                    {(data.assets.sites ?? []).map((s: Row, i: number) => (
                      <li key={i} className="flex justify-between gap-2">
                        <span>{s.domain || '—'} {s.this_site ? <Badge variant="outline">{t('thisSite')}</Badge> : null}</span>
                        <span className="flex items-center gap-2">{s.server?.name ?? ''} {badge(s.status)}</span>
                      </li>
                    ))}
                  </ul>
                  {(data.assets.domains ?? []).length > 0 ? (
                    <div className="text-xs">
                      <span className="text-muted-foreground">{t('domains')}: </span>
                      {(data.assets.domains as Row[]).map((d) => `${d.domain}${d.expires_at ? ` (${date(d.expires_at)})` : ''}`).join('، ')}
                    </div>
                  ) : null}
                  <div className="text-muted-foreground text-xs">
                    {t('hosting')}: {formatNumber((data.assets.hosting ?? []).length)} · {t('integrations')}: {formatNumber(data.assets.integrations_count ?? 0)}
                  </div>
                </Block>
              ) : null}

              {data.billing ? (
                <Block title={t('billing')} testId="customer-hub-billing">
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <span>{t('overdue')}: <b className={data.billing.overdue > 0 ? 'text-destructive' : ''}>{money(data.billing.overdue)}</b></span>
                    <span>{t('revenue12m')}: <b>{money(data.billing.revenue_12m)}</b></span>
                    <span>{t('smsCredit')}: <b>{money(data.billing.sms_credit)}</b></span>
                    <span>{t('lastPayment')}: <b>{data.kpis?.last_payment ? `${money(data.kpis.last_payment.amount)} · ${date(data.kpis.last_payment.date)}` : '—'}</b></span>
                  </div>
                  <div className="text-muted-foreground mt-2 text-xs font-medium">{t('recentInvoices')}</div>
                  {(data.billing.recent_invoices ?? []).length === 0 ? <Empty text={t('none')} /> : (
                    <ul className="space-y-1 text-xs">
                      {(data.billing.recent_invoices as Row[]).map((i) => (
                        <li key={i.id} className="flex justify-between gap-2">
                          <span>{i.number || `#${i.id}`} · {date(i.issue_date)}</span>
                          <span className="flex items-center gap-2">{money(i.total)} {badge(i.status)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                  <div className="text-muted-foreground mt-2 text-xs font-medium">{t('recentPayments')}</div>
                  {(data.billing.recent_payments ?? []).length === 0 ? <Empty text={t('none')} /> : (
                    <ul className="space-y-1 text-xs">
                      {(data.billing.recent_payments as Row[]).map((p) => (
                        <li key={p.id} className="flex justify-between gap-2">
                          <span>{date(p.date)} · {p.gateway || '—'}</span>
                          <span className="flex items-center gap-2">{money(p.amount)} {badge(p.paid ? 'paid' : p.status)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </Block>
              ) : null}

              {data.support ? (
                <Block title={t('support')} testId="customer-hub-support">
                  <div className="text-xs">
                    {t('openTickets')}: <b>{formatNumber(data.support.open)}</b> · {t('slaBreached')}: <b className={data.support.breached ? 'text-destructive' : ''}>{formatNumber(data.support.breached)}</b> · {t('total')}: {formatNumber(data.support.total)}
                  </div>
                  {(data.support.recent ?? []).length === 0 ? <Empty text={t('none')} /> : (
                    <ul className="space-y-1 text-xs">
                      {(data.support.recent as Row[]).map((tk) => (
                        <li key={tk.id} className="flex justify-between gap-2">
                          <span>{tk.number ? `${tk.number} · ` : ''}{tk.subject}</span>
                          <span className="flex items-center gap-2">{date(tk.created_at)} {badge(tk.sla_breached ? 'overdue' : tk.status)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </Block>
              ) : null}

              {data.usage ? (
                <Block title={t('usage')} testId="customer-hub-usage">
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <span>{t('siteOrders')}: <b>{data.usage.site_orders ?? data.usage.orders ?? '—'}</b></span>
                    <span>{t('siteOrders30d')}: <b>{data.usage.site_orders_30d ?? '—'}</b></span>
                    <span>{t('siteCustomers')}: <b>{data.usage.site_customers ?? '—'}</b></span>
                    <span>{t('smsSent')}: <b>{formatNumber(data.usage.sms?.sent ?? 0)}</b></span>
                    <span>{t('storage')}: <b>{data.usage.storage_mb != null ? `${formatNumber(data.usage.storage_mb)} MB` : '—'}</b></span>
                    <span>{t('lastLogin')}: <b>{date(data.usage.last_login_at)}</b></span>
                  </div>
                  <div className="text-muted-foreground text-xs">{t('collectedAt')}: {date(data.usage.collected_at)}</div>
                </Block>
              ) : null}

              {data.sections.includes('site_panel') ? (
                <Block title={t('sitePanel')} testId="customer-hub-site-panel">
                  {panelBusy && !panel ? <Loader2 className="size-4 animate-spin" /> : null}
                  {panel && panel.ok === false ? <Empty text={t('sitePanelDown')} /> : null}
                  {panel?.missing ? <Empty text={t('none')} /> : null}
                  {panel?.ok ? (
                    (panel.matches ?? []).length === 0 ? <Empty text={t('sitePanelNoMatch')} /> : (
                      <ul className="space-y-1 text-xs">
                        {(panel.matches as Row[]).map((m, i) => (
                          <li key={i} className="flex flex-wrap justify-between gap-2">
                            <span>{m.user?.name || m.user?.email || m.user?.mobile || '—'}</span>
                            <span className="flex items-center gap-2">
                              {t('orders')}: {formatNumber((m.orders ?? []).length)} · {t('tickets')}: {formatNumber((m.tickets ?? []).length)} · {t('kyc')}: {badge(m.kyc?.status ?? 'none')}
                            </span>
                          </li>
                        ))}
                      </ul>
                    )
                  ) : null}
                  {data.customer ? (
                    <Button type="button" variant="ghost" size="sm" className="gap-1" disabled={panelBusy} onClick={() => void loadPanel(data.customer!.id, true)}>
                      <RefreshCw className="size-3.5" /> {t('refresh')}
                    </Button>
                  ) : null}
                </Block>
              ) : null}

              {data.timeline ? (
                <Block title={t('timeline')} testId="customer-hub-timeline">
                  {data.timeline.length === 0 ? <Empty text={t('none')} /> : (
                    <ul className="space-y-1 text-xs">
                      {data.timeline.map((e, i) => (
                        <li key={i} className="flex justify-between gap-2">
                          <span>{e.title}</span>
                          <span className="text-muted-foreground">{date(e.date)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </Block>
              ) : null}
            </div>

            {/* quick actions */}
            <div className="flex flex-wrap gap-2 border-t pt-4" data-testid="customer-hub-actions">
              <Button asChild size="sm" className="gap-1.5">
                <Link href={hubHref(data.customer.id)}><ExternalLink className="size-4" />{t('openHub')}</Link>
              </Button>
              {data.billing ? <Button asChild size="sm" variant="outline"><Link href={hubHref(data.customer.id, 'billing')}>{t('billing')}</Link></Button> : null}
              {data.support ? <Button asChild size="sm" variant="outline"><Link href={hubHref(data.customer.id, 'support')}>{t('support')}</Link></Button> : null}
              {data.licenses ? <Button asChild size="sm" variant="outline"><Link href={hubHref(data.customer.id, 'licenses')}>{t('licenses')}</Link></Button> : null}
              {data.can_link ? (
                <Button type="button" size="sm" variant="ghost" disabled={linking} onClick={() => void link(null)} data-testid="customer-hub-unlink">
                  {t('unlink')}
                </Button>
              ) : null}
            </div>
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
