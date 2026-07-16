'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { getCurrentUser, User } from '@/lib/auth';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { AccentBarChart, AccentDonutChart } from '@/components/charts/AccentCharts';
import { useInitialDashboardStats } from '@/lib/initial-dashboard-context';

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

export function DashboardHomePage() {
  const t = useTranslations('dashboard');
  const tCommon = useTranslations('common');
  const initialStats = useInitialDashboardStats();
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState<Stats | null>(initialStats);
  const [full, setFull] = useState<FullDash | null>(null);
  const [teamMember, setTeamMember] = useState<{ tasks_assigned?: number; tickets_assigned?: number } | null>(null);
  const [clientDash, setClientDash] = useState<{ projects?: number } | null>(null);
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

      const results = await Promise.all(requests);
      const [sRes, fRes, pRes, uRes] = results as [typeof results[0], typeof results[1], typeof results[2], typeof results[3]?];

      setStats(unwrapData<Stats>(sRes));
      setFull(unwrapData<FullDash>(fRes));
      setProjects(normalizeListPayload(unwrapData<unknown>(pRes)));
      setTeam(uRes ? normalizeListPayload(unwrapData<unknown>(uRes)) : []);

      if (role === 'team_member') {
        const tm = await apiClient.get('/v1/core/dashboard/stats/team-member');
        setTeamMember(unwrapData<{ tasks_assigned?: number; tickets_assigned?: number }>(tm));
      } else {
        setTeamMember(null);
      }
      if (role === 'client') {
        const cl = await apiClient.get('/v1/core/dashboard/stats/client');
        setClientDash(unwrapData<{ projects?: number }>(cl));
      } else {
        setClientDash(null);
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

  const maxBar = Math.max(
    merged.leads,
    merged.projects,
    merged.tasks,
    merged.tickets,
    merged.contracts,
    1,
  );

  const donutSegments = useMemo(
    () => [
      { label: t('stats.users'), value: merged.leads, color: '#3b82f6' },
      { label: t('stats.projects'), value: merged.projects, color: '#10b981' },
      { label: t('stats.tasks'), value: merged.tasks, color: '#f59e0b' },
      { label: t('stats.tickets'), value: merged.tickets, color: '#f43f5e' },
      { label: t('stats.revenue'), value: merged.contracts, color: '#8b5cf6' },
    ],
    [merged, t],
  );

  function summarizeWidgetRow(it: WidgetItem): string {
    const title = (it.title ?? it.subject ?? it.name ?? it.label) as string | undefined;
    if (title) return String(title);
    return JSON.stringify(it).slice(0, 80);
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm text-muted-foreground">{t('roleLabel')}:</span>
        <Badge variant="secondary">{roleLabel}</Badge>
        {user?.name ? (
          <span className="text-sm text-muted-foreground">
            {user.name} ({user.email})
          </span>
        ) : null}
      </div>

      {role === 'team_member' && teamMember ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>{t('teamMember.tasksAssigned')}</CardDescription>
              <CardTitle className="text-3xl tabular-nums">
                {loading ? '…' : teamMember.tasks_assigned ?? 0}
              </CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardDescription>{t('teamMember.ticketsAssigned')}</CardDescription>
              <CardTitle className="text-3xl tabular-nums">
                {loading ? '…' : teamMember.tickets_assigned ?? 0}
              </CardTitle>
            </CardHeader>
          </Card>
        </div>
      ) : null}

      {role === 'client' && clientDash ? (
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t('client.projects')}</CardDescription>
            <CardTitle className="text-3xl tabular-nums">{clientDash.projects ?? 0}</CardTitle>
          </CardHeader>
        </Card>
      ) : null}

      {role === 'finance_manager' && financeSummary ? (
        <div className="grid gap-4 sm:grid-cols-3">
          <Card><CardHeader className="pb-2"><CardDescription>{t('finance.summary')}</CardDescription><CardTitle className="text-2xl">{String(financeSummary.total_invoices ?? '—')}</CardTitle></CardHeader></Card>
          <Card><CardHeader className="pb-2"><CardDescription>{t('finance.invoices')}</CardDescription><CardTitle className="text-2xl">{String(financeSummary.invoices_count ?? '—')}</CardTitle></CardHeader></Card>
          <Card><CardHeader className="pb-2"><CardDescription>{t('finance.receipts')}</CardDescription><CardTitle className="text-2xl">{String(financeSummary.receipts_count ?? '—')}</CardTitle></CardHeader></Card>
        </div>
      ) : null}

      {role === 'sales_consultant' && salesSummary ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Card><CardHeader className="pb-2"><CardDescription>{t('sales.openDeals')}</CardDescription><CardTitle className="text-3xl tabular-nums">{salesSummary.open_deals ?? 0}</CardTitle></CardHeader></Card>
          <Card><CardHeader className="pb-2"><CardDescription>{t('sales.newLeads')}</CardDescription><CardTitle className="text-3xl tabular-nums">{salesSummary.new_leads ?? 0}</CardTitle></CardHeader></Card>
        </div>
      ) : null}

      {role === 'system_manager' && adminSummary ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Card><CardHeader className="pb-2"><CardDescription>{t('admin.licenses')}</CardDescription><CardTitle className="text-3xl tabular-nums">{adminSummary.licenses ?? 0}</CardTitle></CardHeader></Card>
          <Card><CardHeader className="pb-2"><CardDescription>{t('admin.visitors')}</CardDescription><CardTitle className="text-3xl tabular-nums">{adminSummary.visitors ?? 0}</CardTitle></CardHeader></Card>
        </div>
      ) : null}

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">{t('tabs.overview')}</TabsTrigger>
          {showTeamTab ? <TabsTrigger value="team">{t('tabs.teamProjects')}</TabsTrigger> : null}
        </TabsList>
        <TabsContent value="overview" className="space-y-4">
          {showFullOverview ? (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            {[
              { label: t('stats.users'), value: merged.leads, key: 'leads' },
              { label: t('stats.projects'), value: merged.projects, key: 'projects' },
              { label: t('stats.tasks'), value: merged.tasks, key: 'tasks' },
              { label: t('stats.tickets'), value: merged.tickets, key: 'tickets' },
              { label: t('stats.revenue'), value: merged.contracts, key: 'contracts' },
            ].map((card) => (
              <Card key={card.key}>
                <CardHeader className="pb-2">
                  <CardDescription>{card.label}</CardDescription>
                  <CardTitle className="text-3xl tabular-nums">
                    {loading ? '…' : error ? '—' : card.value}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <Progress value={Math.min(100, (card.value / maxBar) * 100)} />
                </CardContent>
              </Card>
            ))}
          </div>
          ) : null}

          {showFullOverview ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('charts.monthlyActivity')}</CardTitle>
            </CardHeader>
            <CardContent>
              <AccentBarChart
                data={[
                  { label: t('stats.users'), value: merged.leads },
                  { label: t('stats.projects'), value: merged.projects },
                  { label: t('stats.tasks'), value: merged.tasks },
                  { label: t('stats.tickets'), value: merged.tickets },
                  { label: t('stats.revenue'), value: merged.contracts },
                ]}
              />
            </CardContent>
          </Card>
          ) : null}

          {showFullOverview ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('charts.statusDistribution')}</CardTitle>
            </CardHeader>
            <CardContent>
              <AccentDonutChart segments={donutSegments} />
            </CardContent>
          </Card>
          ) : null}

          {visibleWidgets.length > 0 ? (
            <div className="grid gap-4 lg:grid-cols-3">
              {visibleWidgets.map((w) => (
                <Card key={w.id}>
                  <CardHeader>
                    <CardTitle className="text-base">{w.title}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <ul className="space-y-2 text-sm">
                      {(w.items ?? []).slice(0, 8).map((it, idx) => (
                        <li key={idx} className="rounded-md border border-border/60 px-2 py-1.5">
                          {summarizeWidgetRow(it)}
                        </li>
                      ))}
                      {!(w.items && w.items.length) ? (
                        <li className="text-muted-foreground">{tCommon('empty')}</li>
                      ) : null}
                    </ul>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : null}
        </TabsContent>

        {showTeamTab ? (
        <TabsContent value="team">
          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('recentProjects')}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-muted-foreground">
                      <th className="py-2 text-start">{tCommon('search').replace('…', '')}</th>
                      <th className="py-2 text-start">{t('stats.tasks')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {projects.map((p) => (
                      <tr key={String(p.id)} className="border-b border-border/60">
                        <td className="py-2">{String(p.name ?? tCommon('emptyValue'))}</td>
                        <td className="py-2">{String(p.status ?? tCommon('emptyValue'))}</td>
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
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('recentUsers')}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-muted-foreground">
                      <th className="py-2 text-start">{tCommon('search').replace('…', '')}</th>
                      <th className="py-2 text-start">Email</th>
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
              </CardContent>
            </Card>
          </div>
        </TabsContent>
        ) : null}
      </Tabs>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  );
}
