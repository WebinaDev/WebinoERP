'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { getCurrentUser, User } from '@/lib/auth';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Donut, HomeCard, KpiTile, Ring, WD_CHART, compactNumber } from '@/components/charts/HomeCharts';
import { localizeToken } from '@/features/modules/dashboard/localize';
import { useInitialDashboardStats } from '@/lib/initial-dashboard-context';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';
import {
  BriefcaseBusiness,
  ChartColumn,
  ClipboardList,
  FolderKanban,
  ListTodo,
  Settings2,
  Ticket,
  ChartPie as PieChartIcon,
  KeyRound,
  Eye,
  Wallet,
  Receipt,
  FileText,
  Handshake,
  UserPlus,
  CircleDot,
  UserRound,
  UsersRound,
  type LucideIcon,
} from 'lucide-react';
import { CustomerPortalPanel } from '@/features/modules/portal/CustomerPortalPanel';
import { CrmPmSnapshot } from '@/features/modules/dashboard/CrmPmSnapshot';
import { GlobalSearchBox } from '@/features/modules/dashboard/GlobalSearchBox';

type Stats = {
  leads_total?: number;
  projects_active?: number;
  tasks_open?: number;
  tickets_open?: number;
};

type WidgetItem = Record<string, unknown>;

type FullDash = {
  stats?: Record<string, number>;
  widgets?: { id: string; title: string; type: string; items?: WidgetItem[] }[];
};

type QuickLink = { href: string; labelKey: string };

function roleQuickLinks(role: string): QuickLink[] {
  if (role === 'system_manager' || role === 'finance_manager') {
    return [
      { href: 'pm/projects', labelKey: 'quickLinks.projects' },
      { href: 'reports', labelKey: 'quickLinks.reports' },
      { href: 'crm/leads', labelKey: 'quickLinks.leads' },
      { href: 'admin/settings', labelKey: 'quickLinks.settings' },
    ];
  }
  if (role === 'sales_consultant') {
    return [
      { href: 'crm/leads', labelKey: 'quickLinks.leads' },
      { href: 'crm/deals', labelKey: 'quickLinks.deals' },
      { href: 'crm/customers', labelKey: 'quickLinks.customers' },
      { href: 'reports', labelKey: 'quickLinks.reports' },
    ];
  }
  if (role === 'hr_manager') {
    return [
      { href: 'hrm/staff', labelKey: 'quickLinks.hrmStaff' },
      { href: 'hrm/payroll', labelKey: 'quickLinks.hrmPayroll' },
      { href: 'hrm/cartable', labelKey: 'quickLinks.hrmCartable' },
      { href: 'hrm/me', labelKey: 'quickLinks.hrmPortal' },
    ];
  }
  if (role === 'team_member') {
    return [
      { href: 'hrm/me', labelKey: 'quickLinks.hrmPortal' },
      { href: 'hrm/my-payroll', labelKey: 'quickLinks.hrmPayslip' },
      { href: 'hrm/leave', labelKey: 'quickLinks.hrmLeave' },
      { href: 'pm/tasks', labelKey: 'quickLinks.tasks' },
      { href: 'pm/projects', labelKey: 'quickLinks.projects' },
      { href: 'crm/tickets', labelKey: 'quickLinks.tickets' },
      { href: 'pm/time-tracking', labelKey: 'quickLinks.time' },
    ];
  }
  if (role === 'client') {
    return [
      { href: 'pm/projects', labelKey: 'quickLinks.projects' },
      { href: 'crm/tickets', labelKey: 'quickLinks.tickets' },
      { href: 'pm/appointments', labelKey: 'quickLinks.appointments' },
      { href: 'profile', labelKey: 'quickLinks.profile' },
    ];
  }
  return [
    { href: 'reports', labelKey: 'quickLinks.reports' },
    { href: 'profile', labelKey: 'quickLinks.profile' },
  ];
}


/** Lucide only — same stroke as MobileBottomNav. */
const ICON_STROKE = 1.75;

const WIDGET_ICONS: Record<string, LucideIcon> = {
  recent_leads: UserPlus,
  recent_tasks: ListTodo,
  recent_tickets: Ticket,
  recent_projects: FolderKanban,
  recent_deals: Handshake,
  recent_invoices: FileText,
};

const QUICK_LINK_ICONS: Record<string, LucideIcon> = {
  'pm/projects': FolderKanban,
  'pm/tasks': ListTodo,
  'pm/time-tracking': BriefcaseBusiness,
  'pm/appointments': BriefcaseBusiness,
  reports: ChartColumn,
  'crm/leads': UsersRound,
  'crm/deals': BriefcaseBusiness,
  'crm/customers': UsersRound,
  'crm/tickets': Ticket,
  'admin/settings': Settings2,
  profile: UserRound,
  'hrm/staff': UsersRound,
  'hrm/payroll': BriefcaseBusiness,
  'hrm/cartable': ClipboardList,
  'hrm/me': UserRound,
  'hrm/my-payroll': BriefcaseBusiness,
  'hrm/leave': ClipboardList,
};

export function DashboardHomePage() {
  const t = useTranslations('dashboard');
  const tCommon = useTranslations('common');
  const { formatNumber, formatDigits, isRtl } = useLocale();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const initialStats = useInitialDashboardStats();
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState<Stats | null>(initialStats);
  const [full, setFull] = useState<FullDash | null>(null);
  const [teamMember, setTeamMember] = useState<{ tasks_assigned?: number; tickets_assigned?: number } | null>(null);
  const [projects, setProjects] = useState<Record<string, unknown>[]>([]);
  const [team, setTeam] = useState<Record<string, unknown>[]>([]);
  const [financeSummary, setFinanceSummary] = useState<Record<string, unknown> | null>(null);
  const [salesSummary, setSalesSummary] = useState<{ open_deals?: number; new_leads?: number } | null>(null);
  const [adminSummary, setAdminSummary] = useState<{ licenses?: number; visitors?: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const u = await getCurrentUser();
      setUser(u);
      const role = u?.dashboard_role ?? 'guest';
      const showTeamTab = role !== 'client';

      const requests: Promise<unknown>[] = [
        initialStats
          ? Promise.resolve({ data: initialStats })
          : apiClient.get('/v1/core/dashboard/stats'),
        apiClient.get('/v1/core/dashboard'),
        apiClient.get('/v1/projects/projects', { params: { per_page: 8 } }),
      ];
      if (showTeamTab) {
        requests.push(apiClient.get('/v1/core/users', { params: { per_page: 8 } }));
      }

      const settled = await Promise.allSettled(requests);
      const pick = (i: number) => (settled[i]?.status === 'fulfilled' ? settled[i].value : null);
      const sRes = pick(0);
      const fRes = pick(1);
      const pRes = pick(2);
      const uRes = pick(3);

      if (sRes) setStats(unwrapData<Stats>(sRes as { data: unknown }));
      if (fRes) setFull(unwrapData<FullDash>(fRes as { data: unknown }));
      if (pRes) setProjects(normalizeListPayload(unwrapData<unknown>(pRes as { data: unknown })));
      setTeam(uRes ? normalizeListPayload(unwrapData<unknown>(uRes as { data: unknown })) : []);
      const failed = settled.filter((r) => r.status === 'rejected');
      if (failed.length === settled.length) {
        setError(getAxiosMessage(failed[0].reason));
      }

      if (role === 'team_member') {
        const tm = await apiClient.get('/v1/core/dashboard/stats/team-member');
        setTeamMember(unwrapData<{ tasks_assigned?: number; tickets_assigned?: number }>(tm));
      } else {
        setTeamMember(null);
      }
      if (role === 'finance_manager') {
        try {
          const fin = await apiClient.get('/v1/accounting/summary');
          setFinanceSummary(unwrapData(fin) as Record<string, unknown>);
        } catch { setFinanceSummary(null); }
      } else {
        setFinanceSummary(null);
      }
      if (role === 'sales_consultant') {
        try {
          const [dealsRes, leadsRes] = await Promise.all([
            apiClient.get('/v1/crm/deals', { params: { per_page: 1, status: 'open' } }),
            apiClient.get('/v1/crm/leads', { params: { per_page: 1 } }),
          ]);
          const dealsBody = dealsRes.data as { meta?: { total?: number } };
          const leadsBody = leadsRes.data as { meta?: { total?: number } };
          setSalesSummary({ open_deals: dealsBody.meta?.total, new_leads: leadsBody.meta?.total });
        } catch { setSalesSummary(null); }
      } else {
        setSalesSummary(null);
      }
      if (role === 'system_manager') {
        try {
          const [licRes, visRes] = await Promise.all([
            apiClient.get('/v1/core/licenses'),
            apiClient.get('/v1/core/visitor-stats', { params: { days: 7 } }),
          ]);
          const licenses = normalizeListPayload(unwrapData(licRes));
          const vis = unwrapData<{ unique_visitors?: number }>(visRes);
          setAdminSummary({ licenses: licenses.length, visitors: vis.unique_visitors });
        } catch { setAdminSummary(null); }
      } else {
        setAdminSummary(null);
      }
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [initialStats]);

  useEffect(() => {
    void load();
  }, [load]);

  const s = stats ?? {};
  const merged = useMemo(() => {
    const extra = full?.stats ?? {};
    return {
      leads: s.leads_total ?? extra.leads ?? 0,
      projects: s.projects_active ?? extra.projects ?? 0,
      tasks: s.tasks_open ?? extra.tasks_open ?? 0,
      tickets: s.tickets_open ?? extra.tickets_open ?? 0,
      contracts: extra.contracts ?? 0,
    };
  }, [s, full]);

  const role = user?.dashboard_role ?? 'guest';
  const roleLabel = t(`roles.${role}` as 'roles.guest');
  const showTeamTab = role !== 'client';
  const showFullOverview = role !== 'client' && role !== 'team_member';
  const quickLinks = useMemo(() => roleQuickLinks(role), [role]);

  const visibleWidgets = useMemo(() => {
    const widgets = full?.widgets ?? [];
    if (role === 'team_member') {
      return widgets.filter((w) => w.id === 'recent_tasks' || w.id === 'recent_tickets');
    }
    if (role === 'client') {
      return widgets.filter((w) => w.id === 'recent_tickets');
    }
    return widgets;
  }, [full?.widgets, role]);

  const cmp = (n: number) => compactNumber(n, locale);

  const donutSegments = useMemo(
    () => [
      { label: t('stats.users'), value: merged.leads, color: WD_CHART.blue },
      { label: t('stats.projects'), value: merged.projects, color: WD_CHART.teal },
      { label: t('stats.tasks'), value: merged.tasks, color: WD_CHART.amber },
      { label: t('stats.tickets'), value: merged.tickets, color: WD_CHART.rose },
      { label: t('stats.revenue'), value: merged.contracts, color: WD_CHART.violet },
    ],
    [merged, t],
  );

  function widgetTitle(w: { id: string; title: string }): string {
    const key = `widgets.${w.id}`;
    const label = t(key as 'widgets.recent_leads');
    return label === key ? w.title : label;
  }

  function summarizeWidgetRow(it: WidgetItem): string {
    const title = (it.title ?? it.subject ?? it.name ?? it.label) as string | undefined;
    if (title) return String(title);
    return JSON.stringify(it).slice(0, 80);
  }

  const labelOf = (value: string) => localizeToken(value, (key) => t(key as 'badges.open'), formatDigits);

  const initial = (user?.name ?? user?.email ?? 'W').trim().charAt(0).toUpperCase();

  return (
    <div className="wd-mobile-home space-y-6 text-start md:space-y-6" dir={isRtl ? 'rtl' : 'ltr'}>
      {/* Mobile chrome: Bale-style hero + lifted quick actions */}
      <div className="md:hidden">
        <header className="wd-mobile-hero">
          <div className="wd-mobile-hero-top">
            <div className="min-w-0">
              <h1>
                {user?.name
                  ? t('welcomeNamed', { name: user.name })
                  : t('welcome')}
              </h1>
              <p className="wd-mobile-sub">{t('heroSubtitle')}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="wd-mobile-hero-badge">{roleLabel}</span>
              <span className="wd-mobile-avatar" aria-hidden>
                {initial}
              </span>
            </div>
          </div>
        </header>
        <div className="wd-mobile-lift">
          <div className="wd-mobile-search">
            <div className="p-2">
              <GlobalSearchBox />
            </div>
          </div>
          <div className="wd-mobile-actions">
            {quickLinks.slice(0, 6).map((link) => {
              const Icon = QUICK_LINK_ICONS[link.href] ?? FolderKanban;
              return (
                <Link
                  key={link.href}
                  href={dashboardHref(locale, link.href)}
                  className="wd-mobile-action"
                >
                  <span className="wd-mobile-action-ic">
                    <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
                  </span>
                  <span>{t(link.labelKey as 'quickLinks.projects')}</span>
                </Link>
              );
            })}
          </div>
        </div>
      </div>

      {/* Desktop formal home header */}
      <header className="wd-home-hero wd-home-hero--desktop relative z-0 hidden space-y-4 md:block">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="wd-desk-home-greeting min-w-0">
            <h1>
              {user?.name ? t('greetingNamed', { name: user.name }) : t('greeting')}
            </h1>
            <p className="sub">{t('deskSubtitle')}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">{roleLabel}</Badge>
            {user?.email ? (
              <span className="text-muted-foreground text-sm" dir="ltr">
                {user.email}
              </span>
            ) : null}
          </div>
        </div>
        <div>
          <div className="mb-2 text-sm font-semibold tracking-tight">{t('quickAccess')}</div>
          <div className="wd-desk-quick">
            {quickLinks.slice(0, 8).map((link) => {
              const Icon = QUICK_LINK_ICONS[link.href] ?? FolderKanban;
              return (
                <Link
                  key={link.href}
                  href={dashboardHref(locale, link.href)}
                  className="wd-desk-quick-item"
                >
                  <span className="wd-desk-quick-ic">
                    <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
                  </span>
                  <span>{t(link.labelKey as 'quickLinks.projects')}</span>
                </Link>
              );
            })}
          </div>
        </div>
      </header>

      {role === 'team_member' && teamMember ? (
        <div className="wd-kpi-grid wd-kpi-grid--2">
          <KpiTile icon={ListTodo} tone="blue" label={t('teamMember.tasksAssigned')} value={loading ? '…' : cmp(teamMember.tasks_assigned ?? 0)} href={dashboardHref(locale, 'pm/tasks')} />
          <KpiTile icon={Ticket} tone="rose" label={t('teamMember.ticketsAssigned')} value={loading ? '…' : cmp(teamMember.tickets_assigned ?? 0)} href={dashboardHref(locale, 'crm/tickets')} />
        </div>
      ) : null}

      {role === 'finance_manager' && financeSummary ? (
        <div className="wd-kpi-grid wd-kpi-grid--3">
          <KpiTile icon={Wallet} tone="teal" label={t('finance.summary')} value={financeSummary.total_invoices == null ? tCommon('emptyValue') : cmp(Number(financeSummary.total_invoices))} />
          <KpiTile icon={FileText} tone="blue" label={t('finance.invoices')} value={financeSummary.invoices_count == null ? tCommon('emptyValue') : cmp(Number(financeSummary.invoices_count))} href={dashboardHref(locale, 'finance/invoices')} />
          <KpiTile icon={Receipt} tone="amber" label={t('finance.receipts')} value={financeSummary.receipts_count == null ? tCommon('emptyValue') : cmp(Number(financeSummary.receipts_count))} href={dashboardHref(locale, 'finance/receipts')} />
        </div>
      ) : null}

      {role === 'sales_consultant' && salesSummary ? (
        <div className="wd-kpi-grid wd-kpi-grid--2">
          <KpiTile icon={Handshake} tone="teal" label={t('sales.openDeals')} value={cmp(salesSummary.open_deals ?? 0)} href={dashboardHref(locale, 'crm/deals')}
            viz={<Ring value={salesSummary.open_deals ?? 0} max={Math.max(1, (salesSummary.open_deals ?? 0) + (salesSummary.new_leads ?? 0))} size={40} color={WD_CHART.teal} label=" " />} />
          <KpiTile icon={UserPlus} tone="violet" label={t('sales.newLeads')} value={cmp(salesSummary.new_leads ?? 0)} href={dashboardHref(locale, 'crm/leads')} />
        </div>
      ) : null}

      {role === 'system_manager' && adminSummary ? (
        <div className="wd-kpi-grid wd-kpi-grid--2">
          <KpiTile icon={KeyRound} tone="ink" label={t('admin.licenses')} value={cmp(adminSummary.licenses ?? 0)} />
          <KpiTile icon={Eye} tone="blue" label={t('admin.visitors')} value={cmp(adminSummary.visitors ?? 0)} />
        </div>
      ) : null}

      {role === 'client' ? <CustomerPortalPanel /> : <CrmPmSnapshot />}

      <Tabs defaultValue="overview" className="mt-2 md:mt-0">
        <TabsList className="mx-0 w-full justify-start overflow-x-auto rounded-xl bg-muted/70 p-1 md:w-auto">
          <TabsTrigger value="overview" className="gap-1.5 rounded-lg"><PieChartIcon className="size-3.5" strokeWidth={ICON_STROKE} />{t('tabs.overview')}</TabsTrigger>
          {showTeamTab ? <TabsTrigger value="team" className="gap-1.5 rounded-lg"><UsersRound className="size-3.5" strokeWidth={ICON_STROKE} />{t('tabs.teamProjects')}</TabsTrigger> : null}
        </TabsList>
        <TabsContent value="overview" className="space-y-4">
          <div className="grid gap-4 lg:grid-cols-3">
            {showFullOverview ? (
              <HomeCard icon={PieChartIcon} title={t('charts.statusDistribution')} isRtl={isRtl}>
                <Donut data={donutSegments} format={(n) => formatNumber(n)} />
              </HomeCard>
            ) : null}
            {visibleWidgets.map((w) => {
              const Icon = WIDGET_ICONS[w.id] ?? CircleDot;
              return (
                <HomeCard key={w.id} icon={Icon} title={widgetTitle(w)} tone="ink" isRtl={isRtl}
                  aside={(w.items?.length ?? 0) > 0 ? <span className="wd-hc-chip">{formatNumber(w.items?.length ?? 0)}</span> : null}>
                  <ul className="space-y-1.5">
                    {(w.items ?? []).slice(0, 5).map((it, idx) => (
                      <li key={idx} className="wd-row">
                        <span className="wd-row-ic"><Icon strokeWidth={ICON_STROKE} /></span>
                        <span className="min-w-0 flex-1 truncate text-sm">{summarizeWidgetRow(it)}</span>
                      </li>
                    ))}
                    {!(w.items && w.items.length) ? (
                      <li className="text-sm text-muted-foreground">{tCommon('empty')}</li>
                    ) : null}
                  </ul>
                </HomeCard>
              );
            })}
          </div>
        </TabsContent>

        {showTeamTab ? (
        <TabsContent value="team">
          <div className="grid gap-4 lg:grid-cols-2">
            <HomeCard icon={FolderKanban} title={t('recentProjects')} href={dashboardHref(locale, 'pm/projects')} isRtl={isRtl}>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-muted-foreground">
                      <th className="py-2 text-start">{t('colName')}</th>
                      <th className="py-2 text-start">{t('stats.tasks')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((p) => (
                      <tr key={String(p.id)} className="border-b border-border/60">
                        <td className="py-2">{String(p.name ?? tCommon('emptyValue'))}</td>
                        <td className="py-2">{p.status ? labelOf(String(p.status)) : tCommon('emptyValue')}</td>
                      </tr>
                    ))}
                    {!projects.length && !loading ? (
                      <tr>
                        <td colSpan={2} className="py-4 text-center text-muted-foreground">
                          {t('empty')}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </HomeCard>
            <HomeCard icon={UsersRound} title={t('recentUsers')} tone="teal" isRtl={isRtl}>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-muted-foreground">
                      <th className="py-2 text-start">{t('colName')}</th>
                      <th className="py-2 text-start">{t('colEmail')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {team.map((u) => (
                      <tr key={String(u.id)} className="border-b border-border/60">
                        <td className="py-2">{String(u.name ?? tCommon('emptyValue'))}</td>
                        <td className="py-2" dir="ltr">
                          {String(u.email ?? tCommon('emptyValue'))}
                        </td>
                      </tr>
                    ))}
                    {!team.length && !loading ? (
                      <tr>
                        <td colSpan={2} className="py-4 text-center text-muted-foreground">
                          {t('empty')}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </HomeCard>
          </div>
        </TabsContent>
        ) : null}
      </Tabs>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  );
}
