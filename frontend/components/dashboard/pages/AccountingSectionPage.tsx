'use client';

import type { ComponentType } from 'react';
import AccDashboard from '@/components/dashboard/pages/accounting/AccDashboard';
import AccChartOfAccounts from '@/components/dashboard/pages/accounting/AccChartOfAccounts';
import AccJournals from '@/components/dashboard/pages/accounting/AccJournals';
import AccLedger from '@/components/dashboard/pages/accounting/AccLedger';
import AccReports from '@/components/dashboard/pages/accounting/AccReports';
import AccFiscalYear from '@/components/dashboard/pages/accounting/AccFiscalYear';
import AccSettings from '@/components/dashboard/pages/accounting/AccSettings';
import AccPersons from '@/components/dashboard/pages/accounting/AccPersons';
import AccProducts from '@/components/dashboard/pages/accounting/AccProducts';
import AccInvoices from '@/components/dashboard/pages/accounting/AccInvoices';
import AccCashAccounts from '@/components/dashboard/pages/accounting/AccCashAccounts';
import AccReceipts from '@/components/dashboard/pages/accounting/AccReceipts';
import AccChecks from '@/components/dashboard/pages/accounting/AccChecks';
import AccWarehouses from '@/components/dashboard/pages/accounting/AccWarehouses';
import AccWarehouseStock from '@/components/dashboard/pages/accounting/AccWarehouseStock';
import AccWarehouseInbound from '@/components/dashboard/pages/accounting/AccWarehouseInbound';
import AccWarehouseOutbound from '@/components/dashboard/pages/accounting/AccWarehouseOutbound';
import AccWarehouseAudit from '@/components/dashboard/pages/accounting/AccWarehouseAudit';

const ROUTES: Record<string, ComponentType> = {
  '': AccDashboard,
  chart: AccChartOfAccounts,
  journals: AccJournals,
  ledger: AccLedger,
  reports: AccReports,
  'fiscal-year': AccFiscalYear,
  settings: AccSettings,
  persons: AccPersons,
  products: AccProducts,
  invoices: AccInvoices,
  'cash-accounts': AccCashAccounts,
  receipts: AccReceipts,
  checks: AccChecks,
  warehouses: AccWarehouses,
  'warehouse-stock': AccWarehouseStock,
  'warehouse-inbound': AccWarehouseInbound,
  'warehouse-outbound': AccWarehouseOutbound,
  'warehouse-audit': AccWarehouseAudit,
};

type Props = {
  segment: string;
};

export function AccountingSectionPage({ segment }: Props) {
  const C = ROUTES[segment] ?? AccDashboard;
  return <C />;
}
