import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/site-builder';

export type BusinessCategory = {
  id: number;
  slug: string;
  name_fa: string;
  name_en: string;
  icon?: string;
  types?: BusinessType[];
};

export type BusinessType = {
  id: number;
  category_id: number;
  slug: string;
  name_fa: string;
  name_en: string;
  theme_preset?: string;
  default_module_slugs?: string[];
  features?: DashboardFeature[];
  packages?: PackageRow[];
};

export type DashboardFeature = {
  id: number;
  slug: string;
  name_fa: string;
  name_en: string;
  module_slug?: string;
  is_addon: boolean;
};

export type PackageRow = {
  id: number;
  sku: string;
  name_fa: string;
  name_en: string;
  business_type_id: number;
  price: number;
  features?: DashboardFeature[];
};

export type ProvisionProgress = {
  phase?: string;
  percent?: number;
  label_fa?: string;
  label_en?: string;
  eta_seconds?: number | null;
  images_cached?: boolean | null;
  updated_at?: string;
};

export type SiteProvision = {
  id: number;
  slug: string;
  domain: string;
  status: string;
  crm_account_id?: number | null;
  wizard_payload?: Record<string, unknown>;
  license?: {
    id?: number;
    license_key?: string;
    status?: string;
    logo_url?: string;
    project_name?: string;
    start_date?: string;
    expires_at?: string;
    created_at?: string;
    max_users?: number;
    meta?: { modules?: string[]; module_matrix?: Record<string, string[]> };
  };
  package?: PackageRow;
  crm_account?: { id: number; name?: string; email?: string } | null;
  error_log?: string;
  progress?: ProvisionProgress | null;
  launched_at?: string;
  ready_at?: string;
};

export type SiteControlPayload = {
  provision: SiteProvision;
  channel: string;
  admin: { name?: string | null; email?: string | null };
  license: {
    id: number;
    license_key: string;
    status?: string;
    domain?: string;
    logo_url?: string | null;
    project_name?: string | null;
    start_date?: string | null;
    expires_at?: string | null;
    created_at?: string | null;
    max_users?: number | null;
    modules: string[];
    module_matrix?: Record<string, string[]>;
    is_expired: boolean;
    days_remaining: number | null;
  } | null;
  update?: {
    target?: string;
    status?: string;
    log?: string;
    error?: string;
    started_at?: string;
    finished_at?: string;
  } | null;
  customer?: { id: number; name?: string; email?: string } | null;
  ssl?: {
    ssl_status?: string | null;
    expires_at?: string | null;
    domain?: string | null;
    log?: string | null;
  } | null;
  stack?: {
    project?: string | null;
    containers?: Record<string, { status?: string; networks?: string[] }>;
    on_webino_sites?: { backend?: boolean; frontend?: boolean };
    caddy_to_backend?: boolean;
    frontend_to_backend?: boolean;
    log?: string | null;
  } | null;
};

export async function fetchCatalog() {
  const res = await apiClient.get(`${BASE}/catalog`);
  return unwrapData<BusinessCategory[]>(res);
}

export async function fetchFeatures() {
  const res = await apiClient.get(`${BASE}/features`);
  return unwrapData<DashboardFeature[]>(res);
}

export async function fetchPackages(businessTypeId?: number) {
  const res = await apiClient.get(`${BASE}/packages`, {
    params: businessTypeId ? { business_type_id: businessTypeId } : undefined,
  });
  return unwrapData<PackageRow[]>(res);
}

export async function fetchProvisions() {
  const res = await apiClient.get(`${BASE}/provisions`);
  const raw = unwrapData<{ data?: SiteProvision[] } | SiteProvision[]>(res);
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === 'object' && 'data' in raw && Array.isArray(raw.data)) return raw.data;
  return [];
}

export async function fetchProvision(id: number) {
  const res = await apiClient.get(`${BASE}/provisions/${id}`);
  return unwrapData<SiteProvision>(res);
}

export async function fetchProvisionControl(id: number) {
  const res = await apiClient.get(`${BASE}/provisions/${id}/control`);
  return unwrapData<SiteControlPayload>(res);
}

export async function updateProvisionAdmin(
  id: number,
  body: { name?: string; email?: string; password?: string },
) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/admin`, body);
  return unwrapData<SiteProvision>(res);
}

export async function updateProvisionModules(
  id: number,
  body: {
    enable?: string[];
    disable?: string[];
    modules?: string[];
    install?: string;
    replace?: boolean;
  },
) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/modules`, body);
  return unwrapData<SiteProvision>(res);
}

export async function setProvisionChannel(id: number, channel: 'beta' | 'stable' | 'latest') {
  const res = await apiClient.post(`${BASE}/provisions/${id}/channel`, { channel });
  return unwrapData<SiteProvision>(res);
}

export async function queueProvisionUpdate(
  id: number,
  target: 'frontend' | 'backend' | 'migrate' | 'full',
) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/update`, { target });
  return unwrapData<SiteProvision>(res);
}

export async function renewProvisionSsl(id: number, force = false) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/ssl/renew`, { force });
  return unwrapData<SiteProvision>(res);
}

export async function createProvision(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/provisions`, body);
  return unwrapData<SiteProvision>(res);
}

export async function updateProvision(id: number, body: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/provisions/${id}`, body);
  return unwrapData<SiteProvision>(res);
}

export async function prepareProvisionLicense(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/prepare-license`);
  return unwrapData<SiteProvision>(res);
}

export async function launchProvision(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/launch`);
  return unwrapData<SiteProvision>(res);
}

export async function pollProvisionStatus(id: number) {
  const res = await apiClient.get(`${BASE}/provisions/${id}/status`);
  return unwrapData<SiteProvision>(res);
}

export async function cancelProvision(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/cancel`);
  return unwrapData<SiteProvision>(res);
}

export async function retryProvision(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/retry`);
  return unwrapData<SiteProvision>(res);
}

export async function startProvision(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/start`);
  return unwrapData<SiteProvision>(res);
}

export async function stopProvision(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/stop`);
  return unwrapData<SiteProvision>(res);
}

export async function fetchProvisionLogs(id: number, tail = 200) {
  const res = await apiClient.get(`${BASE}/provisions/${id}/logs`, { params: { tail } });
  return unwrapData<{ provision_id?: number; slug?: string; logs?: string } | string>(res);
}

export async function saveCategory(body: Partial<BusinessCategory> & { id?: number }) {
  const res = body.id
    ? await apiClient.patch(`${BASE}/categories/${body.id}`, body)
    : await apiClient.post(`${BASE}/categories`, body);
  return unwrapData<BusinessCategory>(res);
}

export async function saveType(body: Partial<BusinessType> & { id?: number; feature_ids?: number[] }) {
  const res = body.id
    ? await apiClient.patch(`${BASE}/types/${body.id}`, body)
    : await apiClient.post(`${BASE}/types`, body);
  return unwrapData<BusinessType>(res);
}

export async function saveFeature(body: Partial<DashboardFeature> & { id?: number }) {
  const res = body.id
    ? await apiClient.patch(`${BASE}/features/${body.id}`, body)
    : await apiClient.post(`${BASE}/features`, body);
  return unwrapData<DashboardFeature>(res);
}

export async function savePackage(body: Partial<PackageRow> & { id?: number; feature_ids?: number[] }) {
  const res = body.id
    ? await apiClient.patch(`${BASE}/packages/${body.id}`, body)
    : await apiClient.post(`${BASE}/packages`, body);
  return unwrapData<PackageRow>(res);
}
