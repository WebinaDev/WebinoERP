'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useLocale } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { dashboardHref } from '@/lib/route-resolver';
import {
  fetchMonitoringOverview,
  fetchAlertEvents,
  acknowledgeAlert,
  resolveAlert,
  fetchTopConsumers,
  fetchQuotaRisks,
  type MonitoringOverview,
  type AlertEvent,
  type TopConsumerItem,
  type QuotaRiskItem,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function PlatformMonitoringPage() {
  const locale = useLocale();
  const isFa = locale === 'fa';
  const [overview, setOverview] = useState<MonitoringOverview | null>(null);
  const [alerts, setAlerts] = useState<AlertEvent[]>([]);
  const [topCpuConsumers, setTopCpuConsumers] = useState<TopConsumerItem[]>([]);
  const [quotaRisks, setQuotaRisks] = useState<QuotaRiskItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [actingId, setActingId] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [ovRes, alRes, tcRes, qrRes] = await Promise.all([
        fetchMonitoringOverview().catch(() => null),
        fetchAlertEvents({ per_page: 50 }).catch(() => null),
        fetchTopConsumers({ metric: 'cpu', limit: 5 }).catch(() => null),
        fetchQuotaRisks().catch(() => null),
      ]);
      if (ovRes) setOverview(ovRes);
      if (alRes) {
        const list = Array.isArray(alRes) ? alRes : (alRes.data ?? []);
        setAlerts(list);
      }
      if (tcRes?.consumers) setTopCpuConsumers(tcRes.consumers);
      if (qrRes?.risks) setQuotaRisks(qrRes.risks);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
    const timer = setInterval(loadData, 30000);
    return () => clearInterval(timer);
  }, [loadData]);

  const handleAck = async (id: number) => {
    setActingId(id);
    try {
      await acknowledgeAlert(id);
      await loadData();
    } finally {
      setActingId(null);
    }
  };

  const handleResolve = async (id: number) => {
    setActingId(id);
    try {
      await resolveAlert(id);
      await loadData();
    } finally {
      setActingId(null);
    }
  };

  const counts = overview?.counts ?? {
    total_servers: 0,
    healthy_servers: 0,
    warning_servers: 0,
    critical_servers: 0,
    open_alerts: 0,
  };

  return (
    <PlatformPageLayout
      title={isFa ? 'مانیتورینگ زیرساخت و سرورها' : 'Infrastructure & Server Monitoring'}
      description={
        isFa
          ? 'جمع‌آوری متریک‌های زنده، بررسی سلامت سرورها و مدیریت هشدارهای سیستم'
          : 'Live server metrics, health telemetry, and threshold-based alert management'
      }
      actions={<RefreshButton onClick={loadData} loading={loading} />}
    >
      <div className="flex justify-end mb-2">
        <Link href={dashboardHref('admin/platform/monitoring/reports', locale)}>
          <Button size="sm" variant="outline">
            {isFa ? 'مشاهده گزارش‌های تحلیلی (Reports)' : 'View Detailed Reports'}
          </Button>
        </Link>
      </div>
      {/* Overview Stat Cards */}
      <div className="grid gap-4 md:grid-cols-5">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              {isFa ? 'کل سرورها' : 'Total Servers'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{counts.total_servers}</div>
          </CardContent>
        </Card>

        <Card className="border-emerald-500/30">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
              {isFa ? 'سرورهای سالم' : 'Healthy Servers'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
              {counts.healthy_servers}
            </div>
          </CardContent>
        </Card>

        <Card className="border-amber-500/30">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-amber-600 dark:text-amber-400">
              {isFa ? 'هشدار ملایم' : 'Warning State'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
              {counts.warning_servers}
            </div>
          </CardContent>
        </Card>

        <Card className="border-rose-500/30">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-rose-600 dark:text-rose-400">
              {isFa ? 'سرورهای بحرانی' : 'Critical Servers'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">
              {counts.critical_servers}
            </div>
          </CardContent>
        </Card>

        <Card className="border-destructive/30">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-destructive">
              {isFa ? 'هشدارهای باز' : 'Open Alerts'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-destructive">
              {counts.open_alerts}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Server Status List */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base font-semibold">
            {isFa ? 'وضعیت زنده سرورها و آخرین متریک‌ها' : 'Live Server Health & Telemetry'}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {!overview?.servers?.length ? (
            <div className="py-6 text-center text-sm text-muted-foreground">
              {isFa ? 'هیچ سروری یافت نشد.' : 'No servers configured yet.'}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm rtl:text-right">
                <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">{isFa ? 'نام سرور' : 'Server Name'}</th>
                    <th className="px-4 py-3">{isFa ? 'آدرس IP' : 'IP Address'}</th>
                    <th className="px-4 py-3">{isFa ? 'وضعیت سلامت' : 'Health'}</th>
                    <th className="px-4 py-3">{isFa ? 'مصرف CPU' : 'CPU Usage'}</th>
                    <th className="px-4 py-3">{isFa ? 'مصرف حافظه' : 'Memory'}</th>
                    <th className="px-4 py-3">{isFa ? 'مصرف دیسک' : 'Disk'}</th>
                    <th className="px-4 py-3">{isFa ? 'عملیات' : 'Actions'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {overview.servers.map((srv) => (
                    <tr key={srv.id} className="hover:bg-muted/30">
                      <td className="px-4 py-3 font-medium">
                        <Link
                          href={dashboardHref(`admin/platform/servers/${srv.id}`, locale)}
                          className="hover:underline text-primary"
                        >
                          {srv.name}
                        </Link>
                      </td>
                      <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{srv.ip}</td>
                      <td className="px-4 py-3">
                        <Badge
                          variant={
                            srv.health_status === 'healthy'
                              ? 'secondary'
                              : srv.health_status === 'warning'
                              ? 'default'
                              : 'destructive'
                          }
                        >
                          {srv.health_status === 'healthy'
                            ? isFa
                              ? 'سالم'
                              : 'Healthy'
                            : srv.health_status === 'warning'
                            ? isFa
                              ? 'هشدار'
                              : 'Warning'
                            : isFa
                            ? 'بحرانی'
                            : 'Critical'}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs">
                            {Math.round(srv.latest_metrics?.cpu_usage ?? 0)}%
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs">
                            {Math.round(srv.latest_metrics?.mem_usage_percent ?? 0)}%
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs">
                            {Math.round(srv.latest_metrics?.disk_usage_percent ?? 0)}%
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <Link href={dashboardHref(`admin/platform/servers/${srv.id}`, locale)}>
                          <Button variant="ghost" size="sm">
                            {isFa ? 'مشاهده سرور' : 'View Server'}
                          </Button>
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Top Consumers & Quota Risk Grid (Phase 4) */}
      <div className="grid gap-4 lg:grid-cols-2">
        {/* Top Consumers Card */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-semibold">
              {isFa ? 'بیشترین مصرف‌کنندگان پردازنده (Top CPU Consumers)' : 'Top CPU Consumers'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!topCpuConsumers.length ? (
              <p className="py-4 text-center text-xs text-muted-foreground">
                {isFa ? 'داده‌ای موجود نیست.' : 'No consumer data.'}
              </p>
            ) : (
              <div className="divide-y text-xs">
                {topCpuConsumers.map((c) => (
                  <div key={c.resource_id} className="py-2.5 flex items-center justify-between">
                    <div>
                      <span className="font-semibold text-primary">{c.resource_name}</span>
                      <span className="text-muted-foreground ms-2">({c.project_name})</span>
                    </div>
                    <span className="font-mono font-bold">{Math.round(c.value)}%</span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Quota Risk Card */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-semibold">
              {isFa ? 'ریسک سقف منابع (Quota Risks)' : 'Quota Breach Risks'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!quotaRisks.length ? (
              <p className="py-4 text-center text-xs text-muted-foreground">
                {isFa ? 'هیچ منبعی در مرز سقف مجاز نیست.' : 'All resources operating safely within quotas.'}
              </p>
            ) : (
              <div className="divide-y text-xs">
                {quotaRisks.slice(0, 5).map((r, i) => (
                  <div key={i} className="py-2.5 flex items-center justify-between">
                    <div>
                      <Badge variant={r.severity === 'critical' ? 'destructive' : 'default'} className="me-2 text-[10px]">
                        {r.severity}
                      </Badge>
                      <span className="font-semibold">{r.name}</span>
                      <span className="text-muted-foreground ms-1">({r.metric})</span>
                    </div>
                    <span className="font-mono font-bold">{r.usage_percent}%</span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Alert Events Feed */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base font-semibold">
            {isFa ? 'هشدارهای ثبت‌شده' : 'Recorded Alert Events'}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {alerts.length === 0 ? (
            <div className="py-6 text-center text-sm text-muted-foreground">
              {isFa ? 'هیچ هشداری ثبت نشده است.' : 'No alerts have triggered.'}
            </div>
          ) : (
            <div className="divide-y">
              {alerts.map((al) => (
                <div key={al.id} className="flex flex-col gap-2 py-3 md:flex-row md:items-center md:justify-between">
                  <div>
                    <div className="flex items-center gap-2">
                      <Badge
                        variant={
                          al.severity === 'critical'
                            ? 'destructive'
                            : al.severity === 'warning'
                            ? 'default'
                            : 'secondary'
                        }
                      >
                        {al.severity}
                      </Badge>
                      <Badge variant="outline">{al.status}</Badge>
                      <span className="font-medium text-sm">{al.rule_name || `Rule #${al.rule_id}`}</span>
                      {al.server_name && (
                        <span className="text-xs text-muted-foreground">
                          ({isFa ? 'سرور: ' : 'Server: '}
                          {al.server_name})
                        </span>
                      )}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">{al.message}</p>
                    <span className="text-[11px] text-muted-foreground/80">
                      {al.opened_at ? new Date(al.opened_at).toLocaleString(locale) : ''}
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    {al.status === 'open' && (
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={actingId === al.id}
                        onClick={() => handleAck(al.id)}
                      >
                        {isFa ? 'تأیید هشدار' : 'Acknowledge'}
                      </Button>
                    )}
                    {al.status !== 'resolved' && (
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={actingId === al.id}
                        onClick={() => handleResolve(al.id)}
                      >
                        {isFa ? 'برطرف‌شد' : 'Resolve'}
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </PlatformPageLayout>
  );
}
