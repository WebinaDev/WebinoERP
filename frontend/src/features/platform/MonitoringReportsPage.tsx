'use client';

import { useCallback, useEffect, useState } from 'react';
import { useLocale } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  fetchUsageReport,
  fetchTopConsumers,
  fetchQuotaRisks,
  fetchAlertsSummary,
  fetchServers,
  fetchProjects,
  type TopConsumerItem,
  type QuotaRiskItem,
  type AlertsSummaryResponse,
  type PlatformServer,
  type PlatformProject,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function PlatformMonitoringReportsPage() {
  const locale = useLocale();
  const isFa = locale === 'fa';

  const [reportType, setReportType] = useState<'usage' | 'top_consumers' | 'quota_risk' | 'alerts'>('usage');
  const [servers, setServers] = useState<PlatformServer[]>([]);
  const [projects, setProjects] = useState<PlatformProject[]>([]);

  // Filter state
  const [serverId, setServerId] = useState<string>('');
  const [projectId, setProjectId] = useState<string>('');
  const [metricType, setMetricType] = useState<'cpu' | 'mem' | 'disk'>('cpu');
  const [groupBy, setGroupBy] = useState<'hour' | 'day'>('day');
  const [loading, setLoading] = useState(false);

  // Data states
  const [usageSeries, setUsageSeries] = useState<any[]>([]);
  const [topConsumers, setTopConsumers] = useState<TopConsumerItem[]>([]);
  const [quotaRisks, setQuotaRisks] = useState<QuotaRiskItem[]>([]);
  const [alertsSummary, setAlertsSummary] = useState<AlertsSummaryResponse | null>(null);

  useEffect(() => {
    fetchServers().then(setServers).catch(() => {});
    fetchProjects().then(setProjects).catch(() => {});
  }, []);

  const loadReport = useCallback(async () => {
    setLoading(true);
    try {
      if (reportType === 'usage') {
        const res = await fetchUsageReport({
          server_id: serverId || undefined,
          project_id: projectId || undefined,
          group_by: groupBy,
        });
        setUsageSeries(res.series ?? []);
      } else if (reportType === 'top_consumers') {
        const res = await fetchTopConsumers({
          metric: metricType,
          server_id: serverId || undefined,
          project_id: projectId || undefined,
          limit: 15,
        });
        setTopConsumers(res.consumers ?? []);
      } else if (reportType === 'quota_risk') {
        const res = await fetchQuotaRisks();
        setQuotaRisks(res.risks ?? []);
      } else if (reportType === 'alerts') {
        const res = await fetchAlertsSummary({
          server_id: serverId || undefined,
          project_id: projectId || undefined,
        });
        setAlertsSummary(res);
      }
    } finally {
      setLoading(false);
    }
  }, [reportType, serverId, projectId, metricType, groupBy]);

  useEffect(() => {
    loadReport();
  }, [loadReport]);

  const exportCsv = () => {
    const url = `/api/v1/platform/monitoring/reports/usage?export=csv&group_by=${groupBy}${
      serverId ? `&server_id=${serverId}` : ''
    }${projectId ? `&project_id=${projectId}` : ''}`;
    window.open(url, '_blank');
  };

  return (
    <PlatformPageLayout
      title={isFa ? 'گزارش‌های جامع مانیتورینگ و منابع' : 'Platform Monitoring & Usage Reports'}
      description={
        isFa
          ? 'تحلیل تاریخی مصرف منابع، مصرف‌کنندگان برتر، ریسک سقف‌ها و خلاصه‌ی هشدارها'
          : 'Historical usage rollups, top resource consumers, quota breach risks, and alert analytics'
      }
      actions={
        <div className="flex gap-2">
          {reportType === 'usage' && (
            <Button size="sm" variant="outline" onClick={exportCsv}>
              {isFa ? 'خروجی CSV' : 'Export CSV'}
            </Button>
          )}
          <RefreshButton onClick={loadReport} loading={loading} />
        </div>
      }
    >
      {/* Report Selector & Filters Bar */}
      <Card>
        <CardContent className="grid gap-3 pt-6 sm:grid-cols-2 md:grid-cols-4">
          <div className="grid gap-1">
            <Label className="text-xs">{isFa ? 'نوع گزارش' : 'Report Type'}</Label>
            <select
              className="rounded-md border p-2 text-sm bg-background"
              value={reportType}
              onChange={(e) => setReportType(e.target.value as any)}
            >
              <option value="usage">{isFa ? 'مصرف سری زمانی (Usage Rollup)' : 'Time-series Usage'}</option>
              <option value="top_consumers">{isFa ? 'بیشترین مصرف‌کنندگان (Top Consumers)' : 'Top Consumers'}</option>
              <option value="quota_risk">{isFa ? 'ریسک سقف منابع (Quota Risk)' : 'Quota Risk List'}</option>
              <option value="alerts">{isFa ? 'خلاصه هشدارها (Alerts Summary)' : 'Alerts Analytics'}</option>
            </select>
          </div>

          <div className="grid gap-1">
            <Label className="text-xs">{isFa ? 'فیلتر سرور' : 'Filter Server'}</Label>
            <select
              className="rounded-md border p-2 text-sm bg-background"
              value={serverId}
              onChange={(e) => setServerId(e.target.value)}
            >
              <option value="">{isFa ? 'همه سرورها' : 'All Servers'}</option>
              {servers.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.ip})
                </option>
              ))}
            </select>
          </div>

          <div className="grid gap-1">
            <Label className="text-xs">{isFa ? 'فیلتر پروژه' : 'Filter Project'}</Label>
            <select
              className="rounded-md border p-2 text-sm bg-background"
              value={projectId}
              onChange={(e) => setProjectId(e.target.value)}
            >
              <option value="">{isFa ? 'همه پروژه‌ها' : 'All Projects'}</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>

          {reportType === 'usage' ? (
            <div className="grid gap-1">
              <Label className="text-xs">{isFa ? 'گروه‌بندی زمانی' : 'Group By'}</Label>
              <select
                className="rounded-md border p-2 text-sm bg-background"
                value={groupBy}
                onChange={(e) => setGroupBy(e.target.value as any)}
              >
                <option value="hour">{isFa ? 'ساعتی (Hour)' : 'Hourly'}</option>
                <option value="day">{isFa ? 'روزانه (Day)' : 'Daily'}</option>
              </select>
            </div>
          ) : reportType === 'top_consumers' ? (
            <div className="grid gap-1">
              <Label className="text-xs">{isFa ? 'معیار مصرف' : 'Metric'}</Label>
              <select
                className="rounded-md border p-2 text-sm bg-background"
                value={metricType}
                onChange={(e) => setMetricType(e.target.value as any)}
              >
                <option value="cpu">CPU Usage (%)</option>
                <option value="mem">Memory (RAM)</option>
                <option value="disk">Disk Usage</option>
              </select>
            </div>
          ) : null}
        </CardContent>
      </Card>

      {/* Report 1: Usage Rollup Series */}
      {reportType === 'usage' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              {isFa ? 'جدول تجمیعی سری زمانی مصرف (Rollup)' : 'Time-series Rollup Data'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!usageSeries.length ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {isFa ? 'داده‌ای در این بازه زمانی یافت نشد.' : 'No data records found for this range.'}
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm rtl:text-right">
                  <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3">{isFa ? 'بازه زمانی' : 'Bucket'}</th>
                      <th className="px-4 py-3">{isFa ? 'کلید متریک' : 'Metric Key'}</th>
                      <th className="px-4 py-3">{isFa ? 'سطح' : 'Scope'}</th>
                      <th className="px-4 py-3">{isFa ? 'میانگین' : 'Average'}</th>
                      <th className="px-4 py-3">{isFa ? 'حداکثر' : 'Max'}</th>
                      <th className="px-4 py-3">{isFa ? 'حداقل' : 'Min'}</th>
                      <th className="px-4 py-3">{isFa ? 'تعداد نمونه' : 'Samples'}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {usageSeries.map((row, idx) => (
                      <tr key={idx} className="hover:bg-muted/30">
                        <td className="px-4 py-3 font-mono text-xs">{row.bucket}</td>
                        <td className="px-4 py-3 font-medium text-xs">{row.metric_key}</td>
                        <td className="px-4 py-3">
                          <Badge variant="outline">{row.scope}</Badge>
                        </td>
                        <td className="px-4 py-3 font-mono text-xs">{round(row.avg_value)}</td>
                        <td className="px-4 py-3 font-mono text-xs">{round(row.max_value)}</td>
                        <td className="px-4 py-3 font-mono text-xs">{round(row.min_value)}</td>
                        <td className="px-4 py-3 font-mono text-xs">{row.sample_count}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Report 2: Top Consumers */}
      {reportType === 'top_consumers' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              {isFa ? `بیشترین مصرف‌کنندگان بر اساس ${metricType.toUpperCase()}` : `Top Consumers by ${metricType.toUpperCase()}`}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!topConsumers.length ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {isFa ? 'هیچ منبع مصرف‌کننده‌ای یافت نشد.' : 'No consumer data available.'}
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm rtl:text-right">
                  <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3">{isFa ? 'رتبه' : '#'}</th>
                      <th className="px-4 py-3">{isFa ? 'منبع' : 'Resource'}</th>
                      <th className="px-4 py-3">{isFa ? 'سرور' : 'Server'}</th>
                      <th className="px-4 py-3">{isFa ? 'پروژه' : 'Project'}</th>
                      <th className="px-4 py-3">{isFa ? 'نوع' : 'Type'}</th>
                      <th className="px-4 py-3">{isFa ? 'مقدار مصرف' : 'Usage'}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {topConsumers.map((c, i) => (
                      <tr key={c.resource_id} className="hover:bg-muted/30">
                        <td className="px-4 py-3 font-bold text-xs">{i + 1}</td>
                        <td className="px-4 py-3 font-medium text-primary">{c.resource_name}</td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{c.server_name}</td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{c.project_name}</td>
                        <td className="px-4 py-3">
                          <Badge variant="secondary">{c.resource_type}</Badge>
                        </td>
                        <td className="px-4 py-3 font-mono font-bold text-xs">
                          {metricType === 'cpu'
                            ? `${Math.round(c.value)}%`
                            : c.value > 1024 * 1024 * 1024
                            ? `${(c.value / (1024 * 1024 * 1024)).toFixed(2)} GB`
                            : `${Math.round(c.value / (1024 * 1024))} MB`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Report 3: Quota Risks */}
      {reportType === 'quota_risk' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              {isFa ? 'لیست منابع و پروژه‌های در معرض خطر سقف منابع' : 'Quota Risks & Violations'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!quotaRisks.length ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {isFa ? 'همه منابع و پروژه‌ها در محدوده مجاز مصرف قرار دارند.' : 'All resources are operating within quotas.'}
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm rtl:text-right">
                  <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3">{isFa ? 'سطح' : 'Scope'}</th>
                      <th className="px-4 py-3">{isFa ? 'نام' : 'Name'}</th>
                      <th className="px-4 py-3">{isFa ? 'معیار' : 'Metric'}</th>
                      <th className="px-4 py-3">{isFa ? 'شدت ریسک' : 'Severity'}</th>
                      <th className="px-4 py-3">{isFa ? 'مصرف فعلی' : 'Current'}</th>
                      <th className="px-4 py-3">{isFa ? 'سقف تعیین‌شده' : 'Threshold'}</th>
                      <th className="px-4 py-3">{isFa ? 'درصد پر شدن' : '% Used'}</th>
                      <th className="px-4 py-3">{isFa ? 'عملیات اعمال' : 'Action'}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {quotaRisks.map((r, i) => (
                      <tr key={i} className="hover:bg-muted/30">
                        <td className="px-4 py-3">
                          <Badge variant="outline">{r.type}</Badge>
                        </td>
                        <td className="px-4 py-3 font-medium">{r.name}</td>
                        <td className="px-4 py-3 font-mono text-xs uppercase">{r.metric}</td>
                        <td className="px-4 py-3">
                          <Badge variant={r.severity === 'critical' ? 'destructive' : 'default'}>
                            {r.risk_level === 'hard_breach' ? 'Hard Breach' : 'Soft Warning'}
                          </Badge>
                        </td>
                        <td className="px-4 py-3 font-mono text-xs">{r.current_value} {r.unit}</td>
                        <td className="px-4 py-3 font-mono text-xs">{r.threshold} {r.unit}</td>
                        <td className="px-4 py-3 font-mono font-bold text-xs">{r.usage_percent}%</td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{r.action}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Report 4: Alerts Summary */}
      {reportType === 'alerts' && alertsSummary && (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground">Total Alerts (30 Days)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{alertsSummary.total_alerts}</div>
              </CardContent>
            </Card>

            <Card className="border-destructive/30">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs font-medium text-destructive">Critical Alerts</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-destructive">
                  {alertsSummary.by_severity?.critical ?? 0}
                </div>
              </CardContent>
            </Card>

            <Card className="border-amber-500/30">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs font-medium text-amber-600">Warning Alerts</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-amber-600">
                  {alertsSummary.by_severity?.warning ?? 0}
                </div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base font-semibold">Recent Alerts</CardTitle>
            </CardHeader>
            <CardContent>
              {!alertsSummary.recent_alerts?.length ? (
                <p className="py-4 text-center text-sm text-muted-foreground">No recent alerts recorded.</p>
              ) : (
                <div className="divide-y">
                  {alertsSummary.recent_alerts.map((al) => (
                    <div key={al.id} className="py-3 flex items-center justify-between text-xs">
                      <div>
                        <div className="flex items-center gap-2 mb-1">
                          <Badge variant={al.severity === 'critical' ? 'destructive' : 'default'}>
                            {al.severity}
                          </Badge>
                          <span className="font-semibold">{al.rule_name || `Alert #${al.id}`}</span>
                          {al.server_name && <span className="text-muted-foreground">({al.server_name})</span>}
                        </div>
                        <p className="text-muted-foreground">{al.message}</p>
                      </div>
                      <span className="font-mono text-muted-foreground">
                        {al.opened_at ? new Date(al.opened_at).toLocaleDateString(locale) : ''}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </PlatformPageLayout>
  );
}

function round(val: any): string {
  const num = Number(val);
  return isNaN(num) ? '—' : num.toFixed(2);
}
