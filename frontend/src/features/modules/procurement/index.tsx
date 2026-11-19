'use client';

import { FileText, Gavel, ShoppingCart, PackageCheck } from 'lucide-react';
import { ErpModuleHub, ErpResourcePage, SRC, type Row } from '@/features/shared/erp-resource';

const NS = 'erp.procurement';

export function ProcurementOverviewPage() {
  return (
    <ErpModuleHub
      ns={NS}
      links={[
        { route: 'procurement/requests', titleKey: 'requests', descKey: 'requestsDesc', icon: <FileText className="size-5" /> },
        { route: 'procurement/rfqs', titleKey: 'rfqs', descKey: 'rfqsDesc', icon: <Gavel className="size-5" /> },
        { route: 'procurement/orders', titleKey: 'orders', descKey: 'ordersDesc', icon: <ShoppingCart className="size-5" /> },
        { route: 'procurement/receipts', titleKey: 'receipts', descKey: 'receiptsDesc', icon: <PackageCheck className="size-5" /> },
      ]}
    />
  );
}

const pending = (r: Row) => r.status === 'draft' || r.status === 'submitted';

export function ProcurementRequestsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="requestsDesc"
      endpoint="/v1/procurement/requests"
      createLabel="newRequest"
      columns={[
        { key: 'pr_number', label: 'number' },
        { key: 'department', label: 'department' },
        { key: 'requester.name', label: 'requester' },
        { key: 'priority', label: 'priority', type: 'badge' },
        { key: 'required_by_date', label: 'required_by_date', type: 'date' },
        { key: 'total_estimated_amount', label: 'amount', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'department', label: 'department' },
        { name: 'priority', label: 'priority', type: 'select', options: ['low', 'medium', 'high', 'urgent'], defaultValue: 'medium' },
        { name: 'required_by_date', label: 'required_by_date', type: 'date' },
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies },
        { name: 'branch_id', label: 'branch', type: 'remote', remote: SRC.branches },
        {
          name: 'items',
          label: 'items',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
            { name: 'quantity', label: 'quantity', type: 'number' },
            { name: 'estimated_price', label: 'estimated_price', type: 'number' },
          ],
        },
        { name: 'reason', label: 'reason', type: 'textarea' },
      ]}
      rowActions={[
        { label: 'approve', endpoint: (r) => `/v1/procurement/requests/${r.id}/decide`, body: () => ({ decision: 'approve' }), visible: pending },
        { label: 'reject', endpoint: (r) => `/v1/procurement/requests/${r.id}/decide`, body: () => ({ decision: 'reject' }), visible: pending },
      ]}
    />
  );
}

export function ProcurementRfqsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="rfqsDesc"
      endpoint="/v1/procurement/rfqs"
      createLabel="newRfq"
      columns={[
        { key: 'rfq_number', label: 'number' },
        { key: 'title', label: 'title' },
        { key: 'purchase_request.pr_number', label: 'purchase_request' },
        { key: 'deadline_date', label: 'deadline_date', type: 'date' },
        { key: 'bids', label: 'bids', render: (r) => (Array.isArray(r.bids) ? String(r.bids.length) : '0') },
        { key: 'winner_vendor.name', label: 'winner' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'title', label: 'title', required: true },
        { name: 'pr_id', label: 'purchase_request', type: 'remote', remote: SRC.purchaseRequests },
        { name: 'deadline_date', label: 'deadline_date', type: 'date' },
        { name: 'notes', label: 'notes', type: 'textarea' },
      ]}
      rowActions={[
        {
          label: 'addBid',
          endpoint: (r) => `/v1/procurement/rfqs/${r.id}/bids`,
          visible: (r) => r.status === 'open' || r.status === 'evaluating',
          fields: [
            { name: 'vendor_id', label: 'vendor', type: 'remote', remote: SRC.persons, required: true },
            { name: 'amount', label: 'amount', type: 'number', required: true },
            { name: 'delivery_days', label: 'delivery_days', type: 'number' },
            { name: 'notes', label: 'notes', type: 'textarea' },
          ],
        },
        {
          label: 'award',
          endpoint: (r) => `/v1/procurement/rfqs/${r.id}/award`,
          visible: (r) => r.status === 'evaluating',
          fields: [{ name: 'vendor_id', label: 'vendor', type: 'remote', remote: SRC.persons, required: true }],
        },
      ]}
    />
  );
}

export function ProcurementOrdersPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="ordersDesc"
      endpoint="/v1/procurement/orders"
      createLabel="newOrder"
      columns={[
        { key: 'po_number', label: 'number' },
        { key: 'vendor.name', label: 'vendor' },
        { key: 'order_date', label: 'date', type: 'date' },
        { key: 'expected_delivery_date', label: 'expected_delivery_date', type: 'date' },
        { key: 'total', label: 'amount', type: 'money' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'vendor_id', label: 'vendor', type: 'remote', remote: SRC.persons, required: true },
        { name: 'pr_id', label: 'purchase_request', type: 'remote', remote: SRC.purchaseRequests },
        { name: 'rfq_id', label: 'rfq', type: 'remote', remote: SRC.rfqs },
        { name: 'order_date', label: 'date', type: 'date' },
        { name: 'expected_delivery_date', label: 'expected_delivery_date', type: 'date' },
        { name: 'payment_terms', label: 'payment_terms' },
        {
          name: 'items',
          label: 'items',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
            { name: 'quantity', label: 'quantity', type: 'number' },
            { name: 'unit_price', label: 'unit_price', type: 'number' },
            { name: 'tax_percent', label: 'tax_percent', type: 'number' },
          ],
        },
      ]}
    />
  );
}

export function ProcurementReceiptsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="receiptsDesc"
      endpoint="/v1/procurement/receipts"
      createLabel="newReceipt"
      columns={[
        { key: 'grn_number', label: 'number' },
        { key: 'purchase_order.po_number', label: 'purchase_order' },
        { key: 'purchase_order.vendor.name', label: 'vendor' },
        { key: 'warehouse.name', label: 'warehouse' },
        { key: 'receipt_date', label: 'date', type: 'date' },
        { key: 'receiver.name', label: 'receiver' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'po_id', label: 'purchase_order', type: 'remote', remote: SRC.purchaseOrders, required: true },
        { name: 'warehouse_id', label: 'warehouse', type: 'remote', remote: SRC.warehouses, required: true },
        { name: 'receipt_date', label: 'date', type: 'date' },
        { name: 'delivery_note_ref', label: 'delivery_note_ref' },
        {
          name: 'items',
          label: 'items',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'product_id', label: 'product', type: 'remote', remote: SRC.products },
            { name: 'received_qty', label: 'quantity', type: 'number' },
          ],
        },
      ]}
      rowActions={[{ label: 'match3way', endpoint: () => '/v1/procurement/match-3way', body: (r) => ({ po_id: r.po_id, grn_id: r.id }) }]}
    />
  );
}
