import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { readPage } from '@/lib/list-utils';

const BASE = '/v1/site-builder';

type ComposeResult = {
  exit_code?: number;
  log?: string;
  stdout?: string;
  stderr?: string;
  message?: string;
  stages?: Record<string, boolean>;
};

type ActionWithCompose = {
  data: SiteProvision;
  compose?: ComposeResult;
  message?: string;
};

function readActionWithCompose(res: { data: unknown }): ActionWithCompose {
  const body = res.data;
  if (!body || typeof body !== 'object') {
    return { data: body as SiteProvision };
  }
  const o = body as Record<string, unknown>;
  const meta =
    o.meta && typeof o.meta === 'object' ? (o.meta as Record<string, unknown>) : {};
  const compose = (meta.compose ?? o.compose) as ComposeResult | undefined;
  const message =
    typeof o.message === 'string'
      ? o.message
      : typeof meta.message === 'string'
        ? meta.message
        : undefined;
  const data = ('data' in o ? o.data : o) as SiteProvision;
  return { data, compose, message };
}

function readSiblingMeta(res: { data: unknown }): Record<string, unknown> {
  const body = res.data;
  if (!body || typeof body !== 'object') return {};
  const o = body as Record<string, unknown>;
  if (o.meta && typeof o.meta === 'object') return o.meta as Record<string, unknown>;
  return {};
}

function normalizeInstallStatus(raw: unknown): string {
  if (raw == null) return '';
  if (typeof raw === 'string') return raw;
  if (typeof raw !== 'object') return String(raw);
  const o = raw as Record<string, unknown>;
  const install = o.install && typeof o.install === 'object' ? (o.install as Record<string, unknown>) : null;
  const tenant = o.tenant && typeof o.tenant === 'object' ? (o.tenant as Record<string, unknown>) : null;
  const status = o.status ?? install?.status ?? tenant?.status ?? '';
  return status == null ? '' : String(status);
}

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
  power_state?: 'running' | 'stopped' | 'unknown' | string;
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
  power_state?: 'running' | 'stopped' | 'unknown' | string;
  is_remote?: boolean;
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
  package_modules?: string[];
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
    containers?: Record<string, { status?: string; networks?: string[]; restart_count?: number }>;
    on_webino_sites?: { backend?: boolean; frontend?: boolean };
    db_auth_ok?: boolean;
    backend_self?: boolean;
    readiness_ok?: boolean;
    redis_ok?: boolean;
    caddy_snippet_ok?: boolean;
    caddy_config_has_upstream?: boolean;
    caddy_exec_to_backend?: boolean;
    env_pw_fp?: string;
    backend_pw_fp?: string;
    caddy_to_backend?: boolean;
    frontend_to_backend?: boolean;
    app_log?: string;
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

export async function fetchProvisions(page = 1, perPage = 20) {
  const res = await apiClient.get(`${BASE}/provisions`, { params: { page, per_page: perPage } });
  const { rows, meta } = readPage<SiteProvision>(res.data);
  return {
    data: rows,
    current_page: Number(meta.current_page ?? page),
    last_page: Number(meta.last_page ?? 1),
    total: Number(meta.total ?? rows.length),
  };
}

export async function destroyProvision(id: number) {
  const res = await apiClient.delete(`${BASE}/provisions/${id}`);
  return res.data as { message?: string };
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
  const meta = readSiblingMeta(res);
  return {
    data: unwrapData<SiteProvision>(res),
    tenant: meta.tenant,
    message: typeof (res.data as { message?: string })?.message === 'string'
      ? (res.data as { message?: string }).message
      : undefined,
  };
}

export async function installModuleOnSiteApi(
  id: number,
  slug: string,
  async = true,
) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/modules/install`, { slug, async });
  const meta = readSiblingMeta(res);
  return {
    data: unwrapData(res),
    queued: meta.queued ?? (res.data as { queued?: boolean })?.queued,
  };
}

export async function moduleInstallStatusApi(id: number, slug: string) {
  const res = await apiClient.get(`${BASE}/provisions/${id}/modules/${slug}/status`);
  const raw = unwrapData(res);
  const status = normalizeInstallStatus(raw);
  return {
    raw,
    status,
    install: { status },
    tenant: { status },
  };
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
  const meta = readSiblingMeta(res);
  const message =
    typeof (res.data as { message?: string })?.message === 'string'
      ? (res.data as { message?: string }).message
      : undefined;
  return {
    data: unwrapData<SiteProvision>(res),
    install: meta.install,
    license_sync_error:
      typeof meta.license_sync_error === 'string' ? meta.license_sync_error : undefined,
    message:
      message ??
      (typeof meta.license_sync_error === 'string' ? meta.license_sync_error : undefined),
  };
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

export async function repairProvisionDatabase(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/repair-db`);
  return readActionWithCompose(res);
}

export async function bootstrapProvisionSite(id: number) {
  const res = await apiClient.post(`${BASE}/provisions/${id}/bootstrap`);
  return readActionWithCompose(res);
}

export async function fetchProvisionLogs(id: number, tail = 80) {
  const res = await apiClient.get(`${BASE}/provisions/${id}/logs`, { params: { tail } });
  const raw = unwrapData<{ provision_id?: number; slug?: string; logs?: string } | string>(res);
  if (typeof raw === 'string') return raw;
  if (raw && typeof raw === 'object' && typeof raw.logs === 'string') return raw.logs;
  return '';
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
