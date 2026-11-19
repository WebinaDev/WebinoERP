import type { RemoteSource } from './ErpResourcePage';

/** Shared lookups used by ERP module forms. */
export const SRC = {
  companies: { endpoint: '/v1/org/companies' },
  branches: { endpoint: '/v1/org/branches' },
  users: { endpoint: '/v1/core/users', labelKeys: ['name', 'email'] },
  employees: { endpoint: '/v1/hrm/employees', labelKeys: ['full_name', 'name', 'first_name'] },
  products: { endpoint: '/v1/accounting/products' },
  persons: { endpoint: '/v1/accounting/persons', labelKeys: ['name', 'title', 'full_name'] },
  warehouses: { endpoint: '/v1/scm/warehouses' },
  accounts: { endpoint: '/v1/crm/accounts' },
  contacts: { endpoint: '/v1/crm/contacts', labelKeys: ['full_name', 'name', 'first_name'] },
  assets: { endpoint: '/v1/assets/items', labelKeys: ['name', 'asset_code'] },
  maintenancePlans: { endpoint: '/v1/assets/maintenance-plans', labelKeys: ['title'] },
  installBases: { endpoint: '/v1/field-service/install-bases', labelKeys: ['serial_number', 'device_model'] },
  technicians: { endpoint: '/v1/field-service/technicians', labelKeys: ['user.name', 'zone'] },
  qmPlans: { endpoint: '/v1/qm/plans', labelKeys: ['title'] },
  qmLots: { endpoint: '/v1/qm/lots', labelKeys: ['lot_number'] },
  qmInspections: { endpoint: '/v1/qm/inspections', labelKeys: ['id'] },
  qmNcrs: { endpoint: '/v1/qm/ncrs', labelKeys: ['ncr_number', 'defect_description'] },
  purchaseRequests: { endpoint: '/v1/procurement/requests', labelKeys: ['pr_number', 'department'] },
  rfqs: { endpoint: '/v1/procurement/rfqs', labelKeys: ['rfq_number', 'title'] },
  purchaseOrders: { endpoint: '/v1/procurement/orders', labelKeys: ['po_number'] },
  receipts: { endpoint: '/v1/procurement/receipts', labelKeys: ['grn_number'] },
  gateways: { endpoint: '/v1/device-gateway/gateways' },
  peripherals: { endpoint: '/v1/device-gateway/peripherals' },
} satisfies Record<string, RemoteSource>;
