'use client';

import { ClipboardCheck, Package, Search, AlertOctagon, Wrench, BarChart3, ShieldCheck } from 'lucide-react';
import { useLocale, useTranslations } from 'next-intl';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC, useErpData, type StatItem } from '@/features/shared/erp-resource';

const NS = 'erp.qm';

type Analytics = { total_inspections?: number; pass_rate?: number; scrap_rate?: number; open_ncrs?: number; open_capas?: number; quarantined_lots?: number };

function useAnalyticsItems(a: Analytics | null): StatItem[] {
  const t = useTranslations(NS);
  const locale = useLocale();
  const n = (v?: number) => (v === undefined || v === null ? '-' : Number(v).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US'));
  return [
    { label: t('kpi.inspections'), value: n(a?.total_inspections), icon: <Search className="size-4" /> },
    { label: t('kpi.passRate'), value: a?.pass_rate === undefined ? '-' : `${n(a.pass_rate)}%`, icon: <ShieldCheck className="size-4" /> },
    { label: t('kpi.scrapRate'), value: a?.scrap_rate === undefined ? '-' : `${n(a.scrap_rate)}%`, icon: <AlertOctagon className="size-4" /> },
    { label: t('kpi.openNcrs'), value: n(a?.open_ncrs), icon: <AlertOctagon className="size-4" /> },
    { label: t('kpi.openCapas'), value: n(a?.open_capas), icon: <Wrench className="size-4" /> },
    { label: t('kpi.quarantined'), value: n(a?.quarantined_lots), icon: <Package className="size-4" /> },
  ];
}

const INSPECTION_TYPES = ['incoming', 'in_process', 'final', 'sales_return'];

export function QmOverviewPage() {
  const { data } = useErpData<Analytics>('/v1/qm/analytics');
  const items = useAnalyticsItems(data);
  return (
    <ErpModuleHub
      ns={NS}
      stats={<ErpStatGrid items={items.slice(0, 4)} />}
      links={[
        { route: 'qm/plans', titleKey: 'plans', descKey: 'plansDesc', icon: <ClipboardCheck className="size-5" /> },
        { route: 'qm/lots', titleKey: 'lots', descKey: 'lotsDesc', icon: <Package className="size-5" /> },
        { route: 'qm/inspections', titleKey: 'inspections', descKey: 'inspectionsDesc', icon: <Search className="size-5" /> },
        { route: 'qm/ncrs', titleKey: 'ncrs', descKey: 'ncrsDesc', icon: <AlertOctagon className="size-5" /> },
        { route: 'qm/capas', titleKey: 'capas', descKey: 'capasDesc', icon: <Wrench className="size-5" /> },
        { route: 'qm/analytics', titleKey: 'analytics', descKey: 'analyticsDesc', icon: <BarChart3 className="size-5" /> },
      ]}
    />
  );
}

export function QmPlansPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="plansDesc"
      endpoint="/v1/qm/plans"
      createLabel="newPlan"
      columns={[
        { key: 'title', label: 'title' },
        { key: 'inspection_type', label: 'inspection_type', type: 'badge' },
        { key: 'product.name', label: 'product' },
        { key: 'criteria', label: 'criteria', render: (r) => (Array.isArray(r.criteria) ? String(r.criteria.length) : '-') },
        { key: 'is_active', label: 'is_active', type: 'bool' },
      ]}
      createFields={[
        { name: 'title', label: 'title', required: true },
        { name: 'inspection_type', label: 'inspection_type', type: 'select', options: INSPECTION_TYPES, required: true },
        { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
        {
          name: 'criteria',
          label: 'criteria',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'name', label: 'name', type: 'text' },
            { name: 'min', label: 'min', type: 'number' },
            { name: 'max', label: 'max', type: 'number' },
            { name: 'unit', label: 'unit', type: 'text' },
          ],
        },
        { name: 'instructions', label: 'instructions', type: 'textarea' },
        { name: 'is_active', label: 'is_active', type: 'bool', defaultValue: true },
      ]}
    />
  );
}

export function QmLotsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="lotsDesc"
      endpoint="/v1/qm/lots"
      createLabel="newLot"
      columns={[
        { key: 'lot_number', label: 'lot_number' },
        { key: 'product.name', label: 'product' },
        { key: 'quantity', label: 'quantity', type: 'number' },
        { key: 'manufacture_date', label: 'manufacture_date', type: 'date' },
        { key: 'expiry_date', label: 'expiry_date', type: 'date' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'lot_number', label: 'lot_number', required: true },
        { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products, required: true },
        { name: 'quantity', label: 'quantity', type: 'number', required: true },
        { name: 'manufacture_date', label: 'manufacture_date', type: 'date' },
        { name: 'expiry_date', label: 'expiry_date', type: 'date' },
        { name: 'status', label: 'status', type: 'select', options: ['quarantined', 'released', 'rejected', 'expired'], defaultValue: 'quarantined' },
        { name: 'notes', label: 'notes', type: 'textarea' },
      ]}
      rowActions={[
        {
          label: 'changeStatus',
          method: 'patch',
          endpoint: (r) => `/v1/qm/lots/${r.id}/status`,
          fields: [
            { name: 'status', label: 'status', type: 'select', options: ['quarantined', 'released', 'rejected', 'expired'], required: true },
            { name: 'reason', label: 'reason', type: 'textarea' },
          ],
        },
      ]}
    />
  );
}

export function QmInspectionsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="inspectionsDesc"
      endpoint="/v1/qm/inspections"
      createLabel="newInspection"
      columns={[
        { key: 'id', label: 'number' },
        { key: 'plan.title', label: 'plan' },
        { key: 'product.name', label: 'product' },
        { key: 'lot.lot_number', label: 'lot_number' },
        { key: 'inspection_type', label: 'inspection_type', type: 'badge' },
        { key: 'inspector.name', label: 'inspector' },
        { key: 'verdict', label: 'verdict', type: 'badge' },
        { key: 'created_at', label: 'date', type: 'datetime' },
      ]}
      createFields={[
        { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products, required: true },
        { name: 'plan_id', label: 'plan', type: 'remote', remote: SRC.qmPlans },
        { name: 'lot_id', label: 'lot_number', type: 'remote', remote: SRC.qmLots },
        { name: 'inspection_type', label: 'inspection_type', type: 'select', options: INSPECTION_TYPES, required: true },
        { name: 'sample_size', label: 'sample_size', type: 'number' },
        { name: 'verdict', label: 'verdict', type: 'select', options: ['pending', 'pass', 'conditional', 'fail'], required: true, defaultValue: 'pending' },
        { name: 'remarks', label: 'remarks', type: 'textarea' },
      ]}
    />
  );
}

export function QmNcrsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="ncrsDesc"
      endpoint="/v1/qm/ncrs"
      createLabel="newNcr"
      columns={[
        { key: 'ncr_number', label: 'number' },
        { key: 'product.name', label: 'product' },
        { key: 'severity', label: 'severity', type: 'badge' },
        { key: 'disposition', label: 'disposition', type: 'badge' },
        { key: 'affected_quantity', label: 'quantity', type: 'number' },
        { key: 'assignee.name', label: 'assignee' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products, required: true },
        { name: 'inspection_id', label: 'inspection', type: 'remote', remote: SRC.qmInspections },
        { name: 'lot_id', label: 'lot_number', type: 'remote', remote: SRC.qmLots },
        { name: 'severity', label: 'severity', type: 'select', options: ['minor', 'major', 'critical'], required: true },
        { name: 'disposition', label: 'disposition', type: 'select', options: ['scrap', 'rework', 'return_to_vendor', 'accept_as_is'] },
        { name: 'affected_quantity', label: 'quantity', type: 'number' },
        { name: 'assigned_to', label: 'assignee', type: 'remote', remote: SRC.users },
        { name: 'defect_description', label: 'defect_description', type: 'textarea', required: true },
      ]}
    />
  );
}

export function QmCapasPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="capasDesc"
      endpoint="/v1/qm/capas"
      createLabel="newCapa"
      columns={[
        { key: 'capa_number', label: 'number' },
        { key: 'ncr.ncr_number', label: 'ncr' },
        { key: 'type', label: 'type', type: 'badge' },
        { key: 'owner.name', label: 'owner' },
        { key: 'target_date', label: 'target_date', type: 'date' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'type', label: 'type', type: 'select', options: ['corrective', 'preventive'], required: true },
        { name: 'ncr_id', label: 'ncr', type: 'remote', remote: SRC.qmNcrs },
        { name: 'owner_id', label: 'owner', type: 'remote', remote: SRC.users },
        { name: 'target_date', label: 'target_date', type: 'date' },
        { name: 'root_cause', label: 'root_cause', type: 'textarea', required: true },
        { name: 'action_plan', label: 'action_plan', type: 'textarea', required: true },
      ]}
    />
  );
}

export function QmAnalyticsPage() {
  const t = useTranslations(NS);
  const { data, error } = useErpData<Analytics>('/v1/qm/analytics');
  const items = useAnalyticsItems(data);
  return (
    <div className="space-y-4 text-start">
      <p className="text-sm text-muted-foreground">{t('analyticsDesc')}</p>
      {error ? <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      <ErpStatGrid items={items} />
    </div>
  );
}
