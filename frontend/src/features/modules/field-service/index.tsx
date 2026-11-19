'use client';

import { Cpu, ClipboardList, HardHat, BarChart3, AlertTriangle, CheckCircle2, Wrench } from 'lucide-react';
import { useLocale, useTranslations } from 'next-intl';
import { formatMoney } from '@/lib/money';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC, useErpData, type StatItem } from '@/features/shared/erp-resource';

const NS = 'erp.field_service';

type Report = {
  total_orders?: number;
  completed_orders?: number;
  response_sla_breaches?: number;
  resolution_sla_breaches?: number;
  compliance_rate?: number;
  total_parts_cost?: number;
  warranty_covered_cost?: number;
};

function useReportItems(r: Report | null): StatItem[] {
  const t = useTranslations(NS);
  const locale = useLocale();
  const n = (v?: number) => (v === undefined ? '-' : Number(v).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US'));
  return [
    { label: t('kpi.orders'), value: n(r?.total_orders), icon: <ClipboardList className="size-4" /> },
    { label: t('kpi.completed'), value: n(r?.completed_orders), icon: <CheckCircle2 className="size-4" /> },
    { label: t('kpi.slaBreaches'), value: n((r?.response_sla_breaches ?? 0) + (r?.resolution_sla_breaches ?? 0)), icon: <AlertTriangle className="size-4" /> },
    { label: t('kpi.compliance'), value: r?.compliance_rate === undefined ? '-' : `${n(r.compliance_rate)}%`, icon: <BarChart3 className="size-4" /> },
    { label: t('kpi.partsCost'), value: r ? formatMoney(Number(r.total_parts_cost ?? 0), { locale }) : '-', icon: <Wrench className="size-4" /> },
    { label: t('kpi.warrantyCost'), value: r ? formatMoney(Number(r.warranty_covered_cost ?? 0), { locale }) : '-', icon: <Wrench className="size-4" /> },
  ];
}

export function FieldServiceOverviewPage() {
  const { data } = useErpData<Report>('/v1/field-service/reports');
  const items = useReportItems(data);
  return (
    <ErpModuleHub
      ns={NS}
      stats={<ErpStatGrid items={items.slice(0, 4)} />}
      links={[
        { route: 'field-service/install-base', titleKey: 'installBase', descKey: 'installBaseDesc', icon: <Cpu className="size-5" /> },
        { route: 'field-service/orders', titleKey: 'orders', descKey: 'ordersDesc', icon: <ClipboardList className="size-5" /> },
        { route: 'field-service/technicians', titleKey: 'technicians', descKey: 'techniciansDesc', icon: <HardHat className="size-5" /> },
        { route: 'field-service/reports', titleKey: 'reports', descKey: 'reportsDesc', icon: <BarChart3 className="size-5" /> },
      ]}
    />
  );
}

export function FieldServiceInstallBasePage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="installBaseDesc"
      endpoint="/v1/field-service/install-bases"
      createLabel="newInstallBase"
      columns={[
        { key: 'serial_number', label: 'serial_number' },
        { key: 'device_model', label: 'device_model' },
        { key: 'account.name', label: 'customer' },
        { key: 'install_date', label: 'install_date', type: 'date' },
        { key: 'warranty_end', label: 'warranty_end', type: 'date' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'serial_number', label: 'serial_number', required: true },
        { name: 'device_model', label: 'device_model', required: true },
        { name: 'account_id', label: 'customer', type: 'remote', remote: SRC.accounts },
        { name: 'contact_id', label: 'contact', type: 'remote', remote: SRC.contacts },
        { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
        { name: 'install_date', label: 'install_date', type: 'date' },
        { name: 'warranty_start', label: 'warranty_start', type: 'date' },
        { name: 'warranty_end', label: 'warranty_end', type: 'date' },
        { name: 'status', label: 'status', type: 'select', options: ['active', 'in_repair', 'decommissioned'], defaultValue: 'active' },
        { name: 'installation_address', label: 'address', type: 'textarea' },
      ]}
    />
  );
}

export function FieldServiceOrdersPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="ordersDesc"
      endpoint="/v1/field-service/orders"
      createLabel="newOrder"
      columns={[
        { key: 'order_number', label: 'number' },
        { key: 'installBase.serial_number', label: 'serial_number', render: (r) => String((r.install_base as { serial_number?: string } | undefined)?.serial_number ?? '-') },
        { key: 'account.name', label: 'customer' },
        { key: 'technician.user.name', label: 'technician' },
        { key: 'priority', label: 'priority', type: 'badge' },
        { key: 'scheduled_at', label: 'scheduled_at', type: 'datetime' },
        { key: 'total_cost', label: 'total_cost', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'problem_description', label: 'problem_description', type: 'textarea', required: true },
        { name: 'install_base_id', label: 'serial_number', type: 'remote', remote: SRC.installBases },
        { name: 'account_id', label: 'customer', type: 'remote', remote: SRC.accounts },
        { name: 'priority', label: 'priority', type: 'select', options: ['low', 'medium', 'high', 'critical'], defaultValue: 'medium' },
        { name: 'scheduled_at', label: 'scheduled_at', type: 'date' },
        { name: 'sla_response_hours', label: 'sla_response_hours', type: 'number' },
        { name: 'sla_resolution_hours', label: 'sla_resolution_hours', type: 'number' },
      ]}
      rowActions={[
        {
          label: 'dispatch',
          endpoint: (r) => `/v1/field-service/orders/${r.id}/dispatch`,
          visible: (r) => r.status === 'requested',
          fields: [
            { name: 'technician_id', label: 'technician', type: 'remote', remote: SRC.technicians, required: true },
            { name: 'scheduled_at', label: 'scheduled_at', type: 'date' },
          ],
        },
        {
          label: 'addPart',
          endpoint: (r) => `/v1/field-service/orders/${r.id}/spare-parts`,
          visible: (r) => r.status !== 'completed',
          fields: [
            { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products, required: true },
            { name: 'warehouse_id', label: 'warehouse', type: 'remote', remote: SRC.warehouses },
            { name: 'quantity', label: 'quantity', type: 'number', required: true },
            { name: 'unit_cost', label: 'unit_cost', type: 'number', required: true },
          ],
        },
        {
          label: 'complete',
          endpoint: (r) => `/v1/field-service/orders/${r.id}/complete`,
          visible: (r) => r.status !== 'completed',
          fields: [
            { name: 'resolution_notes', label: 'resolution_notes', type: 'textarea', required: true },
            { name: 'labor_cost', label: 'labor_cost', type: 'number' },
          ],
        },
      ]}
    />
  );
}

export function FieldServiceTechniciansPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="techniciansDesc"
      endpoint="/v1/field-service/technicians"
      createLabel="newTechnician"
      columns={[
        { key: 'user.name', label: 'name' },
        { key: 'zone', label: 'zone' },
        { key: 'phone', label: 'phone' },
        { key: 'skills', label: 'skills', render: (r) => (Array.isArray(r.skills) ? (r.skills as string[]).join('، ') : '-') },
        { key: 'is_available', label: 'is_available', type: 'bool' },
      ]}
      createFields={[
        { name: 'user_id', label: 'user', type: 'remote', remote: SRC.users, required: true },
        { name: 'employee_id', label: 'employee', type: 'remote', remote: SRC.employees },
        { name: 'zone', label: 'zone' },
        { name: 'phone', label: 'phone' },
        { name: 'skills', label: 'skills', type: 'list' },
        { name: 'is_available', label: 'is_available', type: 'bool', defaultValue: true },
      ]}
    />
  );
}

export function FieldServiceReportsPage() {
  const t = useTranslations(NS);
  const { data, error } = useErpData<Report>('/v1/field-service/reports');
  const items = useReportItems(data);
  return (
    <div className="space-y-4 text-start">
      <p className="text-sm text-muted-foreground">{t('reportsDesc')}</p>
      {error ? <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      <ErpStatGrid items={items} />
    </div>
  );
}
