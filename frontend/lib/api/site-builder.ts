import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/site-builder';

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

export type SiteProvision = {
  id: number;
  slug: string;
  domain: string;
  status: string;
  wizard_payload?: Record<string, unknown>;
  license?: { license_key?: string };
  package?: PackageRow;
  error_log?: string;
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
