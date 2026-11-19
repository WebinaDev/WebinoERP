'use client';

import { Boxes, TrendingDown, CalendarClock, Wrench, ShieldCheck } from 'lucide-react';
import { ErpModuleHub, ErpResourcePage, SRC } from '@/features/shared/erp-resource';

const NS = 'erp.assets';

export function AssetsOverviewPage() {
  return (
    <ErpModuleHub
      ns={NS}
      links={[
        { route: 'assets/items', titleKey: 'items', descKey: 'itemsDesc', icon: <Boxes className="size-5" /> },
        { route: 'assets/depreciation', titleKey: 'depreciation', descKey: 'depreciationDesc', icon: <TrendingDown className="size-5" /> },
        { route: 'assets/maintenance-plans', titleKey: 'maintenancePlans', descKey: 'maintenancePlansDesc', icon: <CalendarClock className="size-5" /> },
        { route: 'assets/maintenance-orders', titleKey: 'maintenanceOrders', descKey: 'maintenanceOrdersDesc', icon: <Wrench className="size-5" /> },
        { route: 'assets/insurances', titleKey: 'insurances', descKey: 'insurancesDesc', icon: <ShieldCheck className="size-5" /> },
      ]}
    />
  );
}

export function AssetsItemsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="itemsDesc"
      endpoint="/v1/assets/items"
      createLabel="newAsset"
      columns={[
        { key: 'asset_code', label: 'code' },
        { key: 'name', label: 'name' },
        { key: 'category', label: 'category' },
        { key: 'custodian.first_name', label: 'custodian', render: (r) => {
          const c = r.custodian as { first_name?: string; last_name?: string } | null | undefined;
          return c ? `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || '-' : '-';
        } },
        { key: 'purchase_cost', label: 'purchase_cost', type: 'money' },
        { key: 'accumulated_depreciation', label: 'accumulated_depreciation', type: 'money' },
        { key: 'book_value', label: 'book_value', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'asset_code', label: 'code', required: true },
        { name: 'name', label: 'name', required: true },
        { name: 'category', label: 'category', type: 'select', required: true, options: ['building', 'vehicle', 'machinery', 'it_equipment', 'furniture', 'other'] },
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies },
        { name: 'location', label: 'location' },
        { name: 'custodian_id', label: 'custodian', type: 'remote', remote: SRC.employees },
        { name: 'purchase_date', label: 'purchase_date', type: 'date' },
        { name: 'purchase_cost', label: 'purchase_cost', type: 'number', required: true },
        { name: 'salvage_value', label: 'salvage_value', type: 'number' },
        { name: 'useful_life_months', label: 'useful_life_months', type: 'number' },
        { name: 'depreciation_method', label: 'depreciation_method', type: 'select', options: ['straight_line', 'declining_balance'], defaultValue: 'straight_line' },
        { name: 'notes', label: 'notes', type: 'textarea' },
      ]}
    />
  );
}

export function AssetsDepreciationPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="depreciationDesc"
      endpoint="/v1/assets/depreciation-runs"
      createLabel="runDepreciation"
      columns={[
        { key: 'run_number', label: 'number' },
        { key: 'period_date', label: 'period_date', type: 'date' },
        { key: 'company.name', label: 'company' },
        { key: 'assets_count', label: 'assets_count', type: 'number' },
        { key: 'total_depreciation', label: 'total_depreciation', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'period_date', label: 'period_date', type: 'date', required: true },
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies },
      ]}
    />
  );
}

export function AssetsMaintenancePlansPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="maintenancePlansDesc"
      endpoint="/v1/assets/maintenance-plans"
      createLabel="newPlan"
      columns={[
        { key: 'title', label: 'title' },
        { key: 'asset.name', label: 'asset' },
        { key: 'frequency', label: 'frequency', type: 'badge' },
        { key: 'interval_days', label: 'interval_days', type: 'number' },
        { key: 'next_due_date', label: 'next_due_date', type: 'date' },
        { key: 'is_active', label: 'is_active', type: 'bool' },
      ]}
      createFields={[
        { name: 'asset_id', label: 'asset', type: 'remote', remote: SRC.assets, required: true },
        { name: 'title', label: 'title', required: true },
        { name: 'frequency', label: 'frequency', type: 'select', options: ['daily', 'weekly', 'monthly', 'quarterly', 'annual'] },
        { name: 'interval_days', label: 'interval_days', type: 'number', required: true },
        { name: 'next_due_date', label: 'next_due_date', type: 'date' },
        { name: 'checklist', label: 'checklist', type: 'textarea' },
      ]}
    />
  );
}

export function AssetsMaintenanceOrdersPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="maintenanceOrdersDesc"
      endpoint="/v1/assets/maintenance-orders"
      createLabel="newOrder"
      columns={[
        { key: 'order_number', label: 'number' },
        { key: 'asset.name', label: 'asset' },
        { key: 'type', label: 'type', type: 'badge' },
        { key: 'priority', label: 'priority', type: 'badge' },
        { key: 'scheduled_date', label: 'scheduled_at', type: 'date' },
        { key: 'assignee.name', label: 'assignee' },
        { key: 'total_cost', label: 'total_cost', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'asset_id', label: 'asset', type: 'remote', remote: SRC.assets, required: true },
        { name: 'plan_id', label: 'plan', type: 'remote', remote: SRC.maintenancePlans },
        { name: 'type', label: 'type', type: 'select', options: ['preventive', 'corrective', 'overhaul'], defaultValue: 'corrective' },
        { name: 'priority', label: 'priority', type: 'select', options: ['low', 'medium', 'high', 'critical'], defaultValue: 'medium' },
        { name: 'scheduled_date', label: 'scheduled_at', type: 'date', required: true },
        { name: 'assigned_to', label: 'assignee', type: 'remote', remote: SRC.users },
        { name: 'description', label: 'description', type: 'textarea' },
      ]}
      rowActions={[
        {
          label: 'complete',
          endpoint: (r) => `/v1/assets/maintenance-orders/${r.id}/complete`,
          visible: (r) => r.status !== 'completed',
          fields: [
            { name: 'work_done', label: 'work_done', type: 'textarea', required: true },
            { name: 'labor_cost', label: 'labor_cost', type: 'number' },
            { name: 'parts_cost', label: 'parts_cost', type: 'number' },
          ],
        },
      ]}
    />
  );
}

export function AssetsInsurancesPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="insurancesDesc"
      endpoint="/v1/assets/insurances"
      createLabel="newInsurance"
      columns={[
        { key: 'policy_number', label: 'policy_number' },
        { key: 'asset.name', label: 'asset' },
        { key: 'insurer_name', label: 'insurer_name' },
        { key: 'start_date', label: 'start_date', type: 'date' },
        { key: 'end_date', label: 'end_date', type: 'date' },
        { key: 'premium_amount', label: 'premium_amount', type: 'money' },
        { key: 'coverage_amount', label: 'coverage_amount', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'asset_id', label: 'asset', type: 'remote', remote: SRC.assets, required: true },
        { name: 'policy_number', label: 'policy_number', required: true },
        { name: 'insurer_name', label: 'insurer_name', required: true },
        { name: 'start_date', label: 'start_date', type: 'date', required: true },
        { name: 'end_date', label: 'end_date', type: 'date', required: true },
        { name: 'premium_amount', label: 'premium_amount', type: 'number' },
        { name: 'coverage_amount', label: 'coverage_amount', type: 'number' },
        { name: 'notes', label: 'notes', type: 'textarea' },
      ]}
    />
  );
}
