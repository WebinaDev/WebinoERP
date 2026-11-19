'use client';

import { Building2, GitBranch, Hash, Repeat, BarChart3 } from 'lucide-react';
import { useLocale, useTranslations } from 'next-intl';
import { formatMoney } from '@/lib/money';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC, useErpData, type ErpField, type Row } from '@/features/shared/erp-resource';

const NS = 'erp.org';

type Consolidated = {
  holding_summary?: { companies_count?: number; total_branches?: number; total_sales?: number };
  companies?: Row[];
};

function useConsolidated() {
  return useErpData<Consolidated>('/v1/org/reports/consolidated');
}

export function OrgOverviewPage() {
  const t = useTranslations(NS);
  const locale = useLocale();
  const { data } = useConsolidated();
  const h = data?.holding_summary;
  return (
    <ErpModuleHub
      ns={NS}
      stats={
        <ErpStatGrid
          items={[
            { label: t('companies'), value: h?.companies_count ?? '-', icon: <Building2 className="size-4" /> },
            { label: t('branches'), value: h?.total_branches ?? '-', icon: <GitBranch className="size-4" /> },
            { label: t('consolidatedSales'), value: h ? formatMoney(Number(h.total_sales ?? 0), { locale }) : '-', icon: <BarChart3 className="size-4" /> },
          ]}
        />
      }
      links={[
        { route: 'org/companies', titleKey: 'companies', descKey: 'companiesDesc', icon: <Building2 className="size-5" /> },
        { route: 'org/branches', titleKey: 'branches', descKey: 'branchesDesc', icon: <GitBranch className="size-5" /> },
        { route: 'org/transfers', titleKey: 'transfers', descKey: 'transfersDesc', icon: <Repeat className="size-5" /> },
        { route: 'org/sequences', titleKey: 'sequences', descKey: 'sequencesDesc', icon: <Hash className="size-5" /> },
        { route: 'org/consolidated', titleKey: 'consolidated', descKey: 'consolidatedDesc', icon: <BarChart3 className="size-5" /> },
      ]}
    />
  );
}

const COMPANY_FIELDS: ErpField[] = [
  { name: 'name', label: 'name', required: true },
  { name: 'legal_name', label: 'legal_name' },
  { name: 'english_name', label: 'english_name' },
  { name: 'brand_name', label: 'brand_name' },
  { name: 'national_id', label: 'national_id' },
  { name: 'registration_number', label: 'registration_number' },
  { name: 'economic_code', label: 'economic_code' },
  { name: 'currency_code', label: 'currency_code', type: 'select', options: ['IRR', 'IRT', 'USD', 'EUR', 'AED'] },
  { name: 'phone', label: 'phone' },
  { name: 'email', label: 'email' },
  { name: 'website', label: 'website' },
  { name: 'address', label: 'address', type: 'textarea' },
  { name: 'is_default', label: 'is_default', type: 'bool' },
];

export function OrgCompaniesPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="companiesDesc"
      endpoint="/v1/org/companies"
      createLabel="newCompany"
      columns={[
        { key: 'name', label: 'name' },
        { key: 'legal_name', label: 'legal_name' },
        { key: 'brand_name', label: 'brand_name' },
        { key: 'national_id', label: 'national_id' },
        { key: 'registration_number', label: 'registration_number' },
        { key: 'currency_code', label: 'currency_code' },
        { key: 'is_default', label: 'is_default', type: 'bool' },
      ]}
      createFields={COMPANY_FIELDS}
      rowActions={[
        { label: 'switchHere', endpoint: () => '/v1/org/context/switch', body: (r) => ({ company_id: r.id }) },
        { label: 'edit', endpoint: (r) => `/v1/org/companies/${String(r.id)}`, method: 'put', fields: COMPANY_FIELDS, prefill: true },
      ]}
    />
  );
}

export function OrgBranchesPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="branchesDesc"
      endpoint="/v1/org/branches"
      createLabel="newBranch"
      columns={[
        { key: 'code', label: 'code' },
        { key: 'name', label: 'name' },
        { key: 'company.name', label: 'company' },
        { key: 'type', label: 'type', type: 'badge' },
        { key: 'phone', label: 'phone' },
        { key: 'manager.name', label: 'manager' },
        { key: 'is_active', label: 'is_active', type: 'bool' },
      ]}
      createFields={[
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies, required: true },
        { name: 'name', label: 'name', required: true },
        { name: 'code', label: 'code' },
        { name: 'type', label: 'type', type: 'select', options: ['headquarters', 'regional', 'store', 'warehouse', 'factory'] },
        { name: 'phone', label: 'phone' },
        { name: 'manager_id', label: 'manager', type: 'remote', remote: SRC.users },
        { name: 'address', label: 'address', type: 'textarea' },
        { name: 'is_active', label: 'is_active', type: 'bool', defaultValue: true },
      ]}
    />
  );
}

export function OrgTransfersPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="transfersDesc"
      endpoint="/v1/org/transfers"
      createLabel="newTransfer"
      columns={[
        { key: 'transfer_number', label: 'number' },
        { key: 'type', label: 'type', type: 'badge' },
        { key: 'from_company.name', label: 'from_company' },
        { key: 'to_company.name', label: 'to_company' },
        { key: 'total_amount', label: 'amount', type: 'money' },
        { key: 'transfer_date', label: 'date', type: 'date' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'type', label: 'type', type: 'select', options: ['inter_branch', 'inter_company'], required: true },
        { name: 'transfer_date', label: 'date', type: 'date' },
        { name: 'from_company_id', label: 'from_company', type: 'remote', remote: SRC.companies, required: true },
        { name: 'to_company_id', label: 'to_company', type: 'remote', remote: SRC.companies, required: true },
        { name: 'from_branch_id', label: 'from_branch', type: 'remote', remote: SRC.branches },
        { name: 'to_branch_id', label: 'to_branch', type: 'remote', remote: SRC.branches },
        { name: 'from_warehouse_id', label: 'from_warehouse', type: 'remote', remote: SRC.warehouses },
        { name: 'to_warehouse_id', label: 'to_warehouse', type: 'remote', remote: SRC.warehouses },
        {
          name: 'items',
          label: 'items',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
            { name: 'qty', label: 'quantity', type: 'number' },
            { name: 'unit_price', label: 'unit_price', type: 'number' },
          ],
        },
        { name: 'notes', label: 'notes', type: 'textarea' },
      ]}
      rowActions={[{ label: 'receive', endpoint: (r) => `/v1/org/transfers/${r.id}/receive`, visible: (r) => r.status !== 'received' }]}
    />
  );
}

export function OrgSequencesPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="sequencesDesc"
      endpoint="/v1/org/sequences"
      createLabel="newSequence"
      columns={[
        { key: 'company.name', label: 'company' },
        { key: 'branch.name', label: 'branch' },
        { key: 'document_type', label: 'document_type', type: 'badge' },
        { key: 'prefix', label: 'prefix' },
        { key: 'next_number', label: 'next_number', type: 'number' },
        { key: 'padding_zeros', label: 'padding_zeros', type: 'number' },
      ]}
      createFields={[
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies, required: true },
        { name: 'branch_id', label: 'branch', type: 'remote', remote: SRC.branches },
        { name: 'document_type', label: 'document_type', type: 'select', required: true, options: ['sales_invoice', 'purchase_order', 'journal_entry', 'goods_receipt', 'transfer', 'quote'] },
        { name: 'prefix', label: 'prefix' },
        { name: 'next_number', label: 'next_number', type: 'number', defaultValue: '1' },
        { name: 'padding_zeros', label: 'padding_zeros', type: 'number', defaultValue: '5' },
      ]}
    />
  );
}

export function OrgConsolidatedPage() {
  const t = useTranslations(NS);
  const locale = useLocale();
  const { data, loading, error } = useConsolidated();
  const rows = data?.companies ?? [];
  const h = data?.holding_summary;
  const tf = useTranslations('erp.fields');
  return (
    <div className="space-y-4 text-start">
      <p className="text-sm text-muted-foreground">{t('consolidatedDesc')}</p>
      <ErpStatGrid
        items={[
          { label: t('companies'), value: h?.companies_count ?? '-' },
          { label: t('branches'), value: h?.total_branches ?? '-' },
          { label: t('consolidatedSales'), value: h ? formatMoney(Number(h.total_sales ?? 0), { locale }) : '-' },
        ]}
      />
      {error ? <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      <div className="overflow-x-auto rounded-xl border">
        <table className="w-full min-w-[560px] text-sm">
          <thead className="bg-muted/50 text-xs text-muted-foreground">
            <tr>
              <th className="px-3 py-2.5 text-start">{tf('company')}</th>
              <th className="px-3 py-2.5 text-start">{tf('currency_code')}</th>
              <th className="px-3 py-2.5 text-start">{t('branches')}</th>
              <th className="px-3 py-2.5 text-start">{tf('journal_entries')}</th>
              <th className="px-3 py-2.5 text-start">{tf('sales_total')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                  {loading ? '...' : t('noCompanies')}
                </td>
              </tr>
            ) : (
              rows.map((r) => (
                <tr key={String(r.company_id)} className="border-t">
                  <td className="px-3 py-2.5 font-medium">{String(r.company_name ?? '')}</td>
                  <td className="px-3 py-2.5">{String(r.currency ?? '')}</td>
                  <td className="px-3 py-2.5 tabular-nums">{String(r.branches_count ?? 0)}</td>
                  <td className="px-3 py-2.5 tabular-nums">{String(r.journal_entries_count ?? 0)}</td>
                  <td className="px-3 py-2.5 tabular-nums">{formatMoney(Number(r.sales_total ?? 0), { locale })}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
