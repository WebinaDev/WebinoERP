'use client';

import { useState } from 'react';
import { BarChart3, Bell, Mail, LayoutDashboard, TrendingUp, Wallet, Users, Headphones, Boxes, FolderKanban } from 'lucide-react';
import { useLocale, useTranslations } from 'next-intl';
import { formatMoney } from '@/lib/money';
import { Button } from '@/components/ui/button';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC, useErpData, type StatItem } from '@/features/shared/erp-resource';

const NS = 'erp.bi';

type Kpis = {
  period?: { from?: string; to?: string };
  sales_margin?: { total_sales?: number; gross_margin?: number | null; margin_percent?: number | null };
  inventory_turnover?: { total_units?: number };
  ar_collection?: { outstanding_ar?: number; aging_critical_count?: number };
  lead_conversion?: { total_leads?: number; converted_leads?: number; conversion_rate?: number | null };
  project_profitability?: { active_projects?: number };
  support_sla?: { total_tickets?: number; compliance_rate?: number | null };
};

function useKpiItems(k: Kpis | null): StatItem[] {
  const t = useTranslations(NS);
  const locale = useLocale();
  const money = (v?: number | null) => (v === null || v === undefined ? '-' : formatMoney(Number(v), { locale }));
  const pct = (v?: number | null) => (v === null || v === undefined ? '-' : `${Number(v).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US')}%`);
  const num = (v?: number | null) => (v === null || v === undefined ? '-' : Number(v).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US'));
  return [
    { label: t('kpi.totalSales'), value: money(k?.sales_margin?.total_sales), icon: <TrendingUp className="size-4" /> },
    { label: t('kpi.grossMargin'), value: money(k?.sales_margin?.gross_margin), icon: <BarChart3 className="size-4" /> },
    { label: t('kpi.outstandingAr'), value: money(k?.ar_collection?.outstanding_ar), icon: <Wallet className="size-4" /> },
    { label: t('kpi.overdue90'), value: num(k?.ar_collection?.aging_critical_count), icon: <Wallet className="size-4" /> },
    { label: t('kpi.leadConversion'), value: pct(k?.lead_conversion?.conversion_rate), icon: <Users className="size-4" /> },
    { label: t('kpi.stockUnits'), value: num(k?.inventory_turnover?.total_units), icon: <Boxes className="size-4" /> },
    { label: t('kpi.projects'), value: num(k?.project_profitability?.active_projects), icon: <FolderKanban className="size-4" /> },
    { label: t('kpi.ticketsClosed'), value: pct(k?.support_sla?.compliance_rate), icon: <Headphones className="size-4" /> },
  ];
}

export function BiOverviewPage() {
  const { data } = useErpData<Kpis>('/v1/bi/kpis');
  const items = useKpiItems(data);
  return (
    <ErpModuleHub
      ns={NS}
      stats={<ErpStatGrid items={items.slice(0, 4)} />}
      links={[
        { route: 'bi/kpis', titleKey: 'kpis', descKey: 'kpisDesc', icon: <BarChart3 className="size-5" /> },
        { route: 'bi/dashboards', titleKey: 'dashboards', descKey: 'dashboardsDesc', icon: <LayoutDashboard className="size-5" /> },
        { route: 'bi/subscriptions', titleKey: 'subscriptions', descKey: 'subscriptionsDesc', icon: <Mail className="size-5" /> },
        { route: 'bi/alerts', titleKey: 'alerts', descKey: 'alertsDesc', icon: <Bell className="size-5" /> },
      ]}
    />
  );
}

export function BiKpisPage() {
  const t = useTranslations(NS);
  const [days, setDays] = useState(30);
  const from = new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);
  const { data, error, loading } = useErpData<Kpis>('/v1/bi/kpis', { from });
  const items = useKpiItems(data);
  return (
    <div className="space-y-4 text-start">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-muted-foreground">{t('kpisDesc')}</p>
        <div className="flex flex-wrap gap-2">
          {[7, 30, 90, 365].map((d) => (
            <Button key={d} size="sm" variant={d === days ? 'default' : 'outline'} onClick={() => setDays(d)}>
              {t(`range.d${d}`)}
            </Button>
          ))}
        </div>
      </div>
      {error ? <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      <div className={loading ? 'opacity-60' : ''}>
        <ErpStatGrid items={items} />
      </div>
      <p className="text-xs text-muted-foreground">{t('kpiNote')}</p>
    </div>
  );
}

const ROLES = ['ceo', 'sales', 'operations', 'finance'] as const;

export function BiDashboardsPage() {
  const t = useTranslations(NS);
  const [role, setRole] = useState<(typeof ROLES)[number]>('ceo');
  const { data } = useErpData<Kpis>('/v1/bi/kpis');
  const items = useKpiItems(data);
  const pick: Record<(typeof ROLES)[number], number[]> = { ceo: [0, 1, 4, 7], sales: [0, 4], operations: [5, 6], finance: [0, 2, 3, 1] };
  return (
    <div className="space-y-4 text-start">
      <p className="text-sm text-muted-foreground">{t('dashboardsDesc')}</p>
      <div className="flex flex-wrap gap-2">
        {ROLES.map((r) => (
          <Button key={r} size="sm" variant={r === role ? 'default' : 'outline'} onClick={() => setRole(r)}>
            {t(`roles.${r}`)}
          </Button>
        ))}
      </div>
      <ErpStatGrid items={pick[role].map((i) => items[i])} />
    </div>
  );
}

const REPORT_TYPES = ['sales_margin', 'inventory_turnover', 'ar_aging', 'lead_conversion', 'project_profitability', 'support_sla', 'executive_summary'];

export function BiSubscriptionsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="subscriptionsDesc"
      endpoint="/v1/bi/subscriptions"
      createLabel="newSubscription"
      columns={[
        { key: 'name', label: 'name' },
        { key: 'report_type', label: 'report_type', type: 'badge' },
        { key: 'frequency', label: 'frequency', type: 'badge' },
        { key: 'format', label: 'format' },
        { key: 'last_sent_at', label: 'last_sent_at', type: 'datetime' },
        { key: 'is_active', label: 'is_active', type: 'bool' },
      ]}
      createFields={[
        { name: 'name', label: 'name', required: true },
        { name: 'report_type', label: 'report_type', type: 'select', options: REPORT_TYPES, required: true },
        { name: 'frequency', label: 'frequency', type: 'select', options: ['daily', 'weekly', 'monthly'], required: true },
        { name: 'format', label: 'format', type: 'select', options: ['csv', 'json'], required: true, defaultValue: 'csv' },
        { name: 'recipients', label: 'recipients', type: 'list', required: true },
        { name: 'is_active', label: 'is_active', type: 'bool', defaultValue: true },
      ]}
    />
  );
}

export function BiAlertsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="alertsDesc"
      endpoint="/v1/bi/alerts"
      createLabel="newAlert"
      columns={[
        { key: 'title', label: 'title' },
        { key: 'metric_key', label: 'metric', type: 'badge' },
        { key: 'comparator', label: 'comparator', type: 'badge' },
        { key: 'threshold_value', label: 'threshold', type: 'number' },
        { key: 'severity', label: 'severity', type: 'badge' },
        { key: 'last_triggered_at', label: 'last_triggered_at', type: 'datetime' },
        { key: 'is_active', label: 'is_active', type: 'bool' },
      ]}
      createFields={[
        { name: 'title', label: 'title', required: true },
        { name: 'metric_key', label: 'metric', type: 'select', options: ['low_stock', 'sla_breaches', 'lead_conversion'], required: true },
        { name: 'comparator', label: 'comparator', type: 'select', options: ['gt', 'gte', 'lt', 'lte', 'eq'], required: true },
        { name: 'threshold_value', label: 'threshold', type: 'number', required: true },
        { name: 'severity', label: 'severity', type: 'select', options: ['info', 'warning', 'critical'], required: true, defaultValue: 'warning' },
        { name: 'notify_user_id', label: 'notify_user', type: 'remote', remote: SRC.users },
        { name: 'is_active', label: 'is_active', type: 'bool', defaultValue: true },
      ]}
    />
  );
}
