import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';

const BASE = '/v1/marketplace';

/* -------------------------------------------------------------------------- */
/* Types (CRM-parity shapes, mapped onto ERP payloads)                        */
/* -------------------------------------------------------------------------- */

export interface MarketplaceCategory {
  id: number;
  slug: string;
  name: string;
  sort?: number;
  status?: string;
}

export interface MarketplaceRelease {
  id: number;
  module_id?: number;
  version: string;
  tag_name?: string;
  changelog?: string;
  gitea_release_id?: number | null;
  package_path?: string | null;
  package_source?: string | null;
  status: string;
  published_at?: string | null;
}

export interface MarketplaceModule {
  id: number;
  slug: string;
  name: string;
  description?: string;
  readme_md?: string;
  icon_url?: string;
  detail_url?: string;
  category_id?: number | null;
  parent_module_id?: number | null;
  parent_slug?: string | null;
  parent_name?: string | null;
  package_available?: boolean;
  category_slug?: string;
  category_name?: string;
  price: number;
  currency?: string;
  is_free?: boolean;
  is_builtin?: boolean;
  is_core?: boolean;
  version?: string;
  latest_version?: string;
  latest_updated_at?: string | null;
  settings_area?: string;
  settings_route?: string;
  sort?: number;
  status: string;
  package_source?: string;
  distribution?: string;
  requires_license?: boolean;
  module_git_source_id?: number | null;
  gitea_owner?: string;
  gitea_repo?: string;
  gitea_repo_id?: number | null;
  gitea_repo_url?: string;
  gitea_repo_linked?: boolean;
  gitea_private?: boolean;
  gitea_default_branch?: string;
  gitea_html_url?: string;
  erp_submodule_settings_key?: string;
  latest_release_id?: number | null;
  releases?: MarketplaceRelease[];
  repo?: Record<string, unknown> | null;
}

export interface MarketplaceOrder {
  id: number;
  order_number?: string;
  domain?: string;
  module_slug?: string;
  module_name?: string;
  amount?: number;
  total?: number;
  status: string;
  authority?: string;
  ref_id?: string;
  user_id?: number | null;
  site_provision_id?: number | null;
  created_at?: string;
  items?: Array<Record<string, unknown>>;
}

export type GiteaIpScheme = 'auto' | 'http' | 'https';
export type OrgGitProvider = 'gitea' | 'github' | 'gitlab';

export interface GiteaSettings {
  id?: number;
  provider?: OrgGitProvider;
  host?: string;
  base_url?: string;
  org?: string;
  platform_source_id?: number | null;
  has_token?: boolean;
  /** CRM-compat aliases */
  gitea_base_url?: string;
  gitea_org?: string;
  gitea_ip_override?: string;
  gitea_ip_scheme?: GiteaIpScheme;
  gitea_configured?: boolean;
}

export interface GiteaDiagnosticStep {
  key?: string;
  label?: string;
  url?: string;
  http?: number;
  ok?: boolean;
  hint?: string;
  message?: string;
  ms?: number;
  curl_error?: string;
}

export interface GiteaConnectionDiagnosticsResult {
  ok: boolean;
  message?: string;
  user?: string;
  provider?: string;
  hosts?: string[];
  diag?: {
    base_url?: string;
    api_base?: string;
    org?: string;
    owner?: string;
    owner_kind?: string;
    ip_override?: string;
    ip_scheme?: string;
    host_header?: string;
    token_configured?: boolean;
    resolved_url_sample?: string;
    resolved_scheme?: string;
    sslverify?: boolean;
  };
  steps?: Record<string, GiteaDiagnosticStep>;
  hints?: string[];
}

export interface BasalamOAuthStatus {
  configured?: boolean;
  client_id?: string;
  client_secret?: string;
  redirect_uri?: string;
  scopes?: string;
  token_url?: string;
  sso_url?: string;
  callback_path?: string;
}

export interface BasalamConnection {
  site_url?: string;
  vendor_id?: number;
  status?: 'connected' | 'disconnected' | string;
  connected_at?: string;
  last_seen_at?: string;
  disconnected_at?: string;
}

function asRecord(v: unknown): Record<string, unknown> {
  return v && typeof v === 'object' ? (v as Record<string, unknown>) : {};
}

function listFromPayload(raw: unknown): Record<string, unknown>[] {
  const unwrapped = raw && typeof raw === 'object' && 'data' in (raw as object)
    ? (raw as { data: unknown }).data
    : raw;
  const fromList = normalizeListPayload(unwrapped);
  if (fromList.length) return fromList;
  if (unwrapped && typeof unwrapped === 'object') {
    const o = unwrapped as Record<string, unknown>;
    if (Array.isArray(o.modules)) return o.modules as Record<string, unknown>[];
    if (Array.isArray(o.categories)) return o.categories as Record<string, unknown>[];
    if (Array.isArray(o.orders)) return o.orders as Record<string, unknown>[];
    if (Array.isArray(o.releases)) return o.releases as Record<string, unknown>[];
  }
  return normalizeListPayload(raw);
}

export function normalizeMarketplaceModule(raw: unknown): MarketplaceModule {
  const m = asRecord(raw);
  const repo = asRecord(m.repo);
  const releasesRaw = Array.isArray(m.releases) ? m.releases : [];
  const releases: MarketplaceRelease[] = releasesRaw.map((r) => {
    const row = asRecord(r);
    return {
      id: Number(row.id),
      module_id: row.module_id != null ? Number(row.module_id) : undefined,
      version: String(row.version ?? ''),
      tag_name: row.tag_name != null ? String(row.tag_name) : row.version != null ? `v${row.version}` : undefined,
      changelog: row.changelog != null ? String(row.changelog) : undefined,
      status: String(row.status ?? 'draft'),
      published_at: row.published_at != null ? String(row.published_at) : null,
      gitea_release_id: row.gitea_release_id != null ? Number(row.gitea_release_id) : null,
    };
  });
  const published = releases.find((r) => r.status === 'published') ?? releases[0];
  const price = Number(m.price ?? 0);
  const distribution = String(m.distribution ?? 'git');
  const statusRaw = String(m.status ?? 'draft');
  const status =
    statusRaw === 'published' || statusRaw === 'active' ? 'active' : statusRaw === 'inactive' ? 'inactive' : statusRaw;
  const repoUrl = repo.repo_url != null ? String(repo.repo_url) : '';
  const category = asRecord(m.category);

  return {
    id: Number(m.id),
    slug: String(m.slug ?? ''),
    name: String(m.name ?? ''),
    description: m.description != null ? String(m.description) : undefined,
    readme_md:
      m.readme_md != null
        ? String(m.readme_md)
        : repo.readme_excerpt != null
          ? String(repo.readme_excerpt)
          : '',
    icon_url: m.icon_url != null ? String(m.icon_url) : undefined,
    detail_url: m.detail_url != null ? String(m.detail_url) : undefined,
    category_id: m.category_id != null ? Number(m.category_id) : null,
    category_name: category.name != null ? String(category.name) : m.category_name != null ? String(m.category_name) : undefined,
    category_slug: category.slug != null ? String(category.slug) : undefined,
    parent_module_id: m.parent_module_id != null ? Number(m.parent_module_id) : null,
    parent_name: m.parent_name != null ? String(m.parent_name) : undefined,
    price,
    currency: String(m.currency ?? 'IRT'),
    is_free: price <= 0,
    is_builtin: distribution === 'bundled',
    is_core: Boolean(m.is_core),
    version: published?.version ?? String(m.version ?? '1.0.0'),
    latest_version: published?.version ?? (repo.latest_tag != null ? String(repo.latest_tag) : undefined),
    status,
    package_source: distribution === 'git' ? 'gitea' : 'local',
    distribution,
    requires_license: Boolean(m.requires_license),
    module_git_source_id: m.module_git_source_id != null ? Number(m.module_git_source_id) : null,
    settings_route: m.settings_route != null ? String(m.settings_route) : undefined,
    settings_area: m.settings_area != null ? String(m.settings_area) : 'shop',
    gitea_repo: repo.gitea_repo != null ? String(repo.gitea_repo) : undefined,
    gitea_repo_url: repoUrl || undefined,
    gitea_html_url: repoUrl || undefined,
    gitea_repo_linked: Boolean(repoUrl),
    gitea_default_branch:
      repo.default_branch != null
        ? String(repo.default_branch)
        : repo.repo_branch != null
          ? String(repo.repo_branch)
          : undefined,
    gitea_private: repo.private != null ? Boolean(repo.private) : undefined,
    erp_submodule_settings_key:
      m.erp_submodule_settings_key != null ? String(m.erp_submodule_settings_key) : undefined,
    releases,
    repo,
  };
}

function normalizeCategory(raw: unknown): MarketplaceCategory {
  const c = asRecord(raw);
  return {
    id: Number(c.id),
    slug: String(c.slug ?? ''),
    name: String(c.name ?? ''),
    sort: c.sort != null ? Number(c.sort) : 0,
    status: c.status != null ? String(c.status) : 'active',
  };
}

function normalizeOrder(raw: unknown): MarketplaceOrder {
  const o = asRecord(raw);
  const items = Array.isArray(o.items) ? (o.items as Array<Record<string, unknown>>) : [];
  const first = items[0] ?? {};
  return {
    id: Number(o.id),
    order_number: o.order_number != null ? String(o.order_number) : undefined,
    domain: o.domain != null ? String(o.domain) : undefined,
    module_slug: first.module_slug != null ? String(first.module_slug) : o.module_slug != null ? String(o.module_slug) : undefined,
    module_name: first.module_name != null ? String(first.module_name) : o.module_name != null ? String(o.module_name) : undefined,
    amount: o.total != null ? Number(o.total) : o.amount != null ? Number(o.amount) : 0,
    total: o.total != null ? Number(o.total) : undefined,
    status: String(o.status ?? ''),
    user_id: o.user_id != null ? Number(o.user_id) : null,
    site_provision_id: o.site_provision_id != null ? Number(o.site_provision_id) : null,
    created_at: o.created_at != null ? String(o.created_at) : undefined,
    items,
  };
}

/* -------------------------------------------------------------------------- */
/* Existing helpers (kept working)                                            */
/* -------------------------------------------------------------------------- */

export async function listModules(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/modules`, { params: { per_page: 100, ...params } });
  return unwrapData(res);
}

export async function getModule(id: number | string) {
  const res = await apiClient.get(`${BASE}/modules/${id}`);
  return unwrapData(res);
}

export async function saveModule(data: Record<string, unknown>, id?: number | string) {
  if (id && id !== 'new') {
    const res = await apiClient.put(`${BASE}/modules/${id}`, data);
    return unwrapData(res);
  }
  const res = await apiClient.post(`${BASE}/modules`, data);
  return unwrapData(res);
}

export async function deleteModule(id: number | string) {
  const res = await apiClient.delete(`${BASE}/modules/${id}`);
  return unwrapData(res);
}

export async function syncModuleRepo(id: number | string) {
  const res = await apiClient.post(`${BASE}/modules/${id}/repo/sync`);
  return unwrapData(res);
}

export async function syncModuleReadme(id: number | string) {
  const res = await apiClient.post(`${BASE}/modules/${id}/readme/sync`);
  return unwrapData(res);
}

export async function listModuleReleases(id: number | string) {
  const res = await apiClient.get(`${BASE}/modules/${id}/releases`);
  return unwrapData(res);
}

export async function createModuleRelease(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/modules/${id}/releases`, data);
  return unwrapData(res);
}

export async function publishRelease(id: number | string) {
  const res = await apiClient.post(`${BASE}/releases/${id}/publish`);
  return unwrapData(res);
}

export async function deleteRelease(id: number | string) {
  const res = await apiClient.delete(`${BASE}/releases/${id}`);
  return unwrapData(res);
}

export async function testGiteaConnection(data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/gitea/test`, data ?? {});
  return unwrapData(res);
}

export async function purchaseModule(data: {
  site_provision_id: number;
  module_id?: number;
  module_slug?: string;
  mark_paid?: boolean;
  pay?: boolean;
}) {
  const res = await apiClient.post(`${BASE}/orders/purchase`, data);
  return unwrapData(res);
}

export async function installModuleOnSite(
  siteProvisionId: number | string,
  slug: string,
  async = true,
) {
  const res = await apiClient.post(`/v1/site-builder/provisions/${siteProvisionId}/modules/install`, {
    slug,
    async,
  });
  return unwrapData(res);
}

export async function moduleInstallStatus(siteProvisionId: number | string, slug: string) {
  const res = await apiClient.get(
    `/v1/site-builder/provisions/${siteProvisionId}/modules/${slug}/status`,
  );
  return unwrapData(res);
}

/* -------------------------------------------------------------------------- */
/* CRM-parity client                                                          */
/* -------------------------------------------------------------------------- */

export async function getMarketplaceModules(includeCore = false) {
  const raw = await listModules(includeCore ? { include_core: 1, list: 1 } : { list: 1 });
  const payload = asRecord(raw);
  const moduleRows = Array.isArray(payload.modules)
    ? (payload.modules as Record<string, unknown>[])
    : listFromPayload(raw);
  const categoryRows = Array.isArray(payload.categories)
    ? (payload.categories as Record<string, unknown>[])
    : [];
  const modules = moduleRows
    .map(normalizeMarketplaceModule)
    .filter((m) => (includeCore ? true : !m.is_core));
  const categories: MarketplaceCategory[] = categoryRows.map((c) => {
    const row = asRecord(c);
    return {
      id: Number(row.id),
      slug: String(row.slug ?? ''),
      name: String(row.name ?? ''),
      sort: row.sort != null ? Number(row.sort) : 0,
      status: row.status != null ? String(row.status) : 'active',
    };
  });
  return { modules, categories };
}

export async function getMarketplaceModule(id: number | string) {
  const raw = await getModule(id);
  const payload = asRecord(raw);
  const moduleRaw = payload.module ?? raw;
  return {
    module: normalizeMarketplaceModule(moduleRaw),
    sync_warning: payload.sync_warning != null ? String(payload.sync_warning) : undefined,
  };
}

export async function saveMarketplaceModule(data: Record<string, unknown> | FormData) {
  if (data instanceof FormData) {
    const obj: Record<string, unknown> = {};
    data.forEach((value, key) => {
      if (typeof value === 'string') obj[key] = value;
    });
    const id = obj.id != null && String(obj.id) !== '' ? String(obj.id) : undefined;
    const payload: Record<string, unknown> = {
      name: obj.name,
      slug: obj.slug,
      description: obj.description,
      readme_md: obj.readme_md,
      icon_url: obj.icon_url,
      detail_url: obj.detail_url,
      settings_route: obj.settings_route,
      settings_area: obj.settings_area,
      version: obj.version,
      price: obj.price != null ? Number(obj.price) : 0,
      category_id: obj.category_id ? Number(obj.category_id) : null,
      parent_module_id: obj.parent_module_id ? Number(obj.parent_module_id) : null,
      status: obj.status === 'active' ? 'active' : obj.status === 'inactive' ? 'inactive' : obj.status,
      is_free: obj.is_free === '1' || obj.is_free === true || obj.is_free === 'true',
      is_builtin: obj.is_builtin === '1' || obj.is_builtin === true || obj.is_builtin === 'true',
      is_core: obj.is_core === '1' || obj.is_core === true || obj.is_core === 'true',
      distribution:
        obj.package_source === 'gitea' || obj.use_gitea === '1'
          ? 'git'
          : obj.distribution ?? (obj.is_builtin === '1' ? 'bundled' : 'git'),
      currency: obj.currency ?? 'IRT',
      sort: obj.sort != null ? Number(obj.sort) : undefined,
    };
    if (obj.module_git_source_id) payload.module_git_source_id = Number(obj.module_git_source_id);
    if (obj.requires_license != null) {
      payload.requires_license = obj.requires_license === '1' || obj.requires_license === true;
    }
    if (obj.gitea_owner) payload.gitea_owner = obj.gitea_owner;
    if (obj.gitea_repo) payload.gitea_repo = obj.gitea_repo;
    const saved = await saveModule(payload, id);
    const savedRec = asRecord(saved);
    return {
      id: Number(savedRec.id ?? id ?? 0),
      warnings: savedRec.warnings as Record<string, string> | undefined,
      module: normalizeMarketplaceModule(saved),
    };
  }
  const id =
    data.id != null && String(data.id) !== '' && data.id !== 'new' ? String(data.id) : undefined;
  const saved = await saveModule(data, id);
  const savedRec = asRecord(saved);
  return {
    id: Number(savedRec.id ?? id ?? 0),
    warnings: savedRec.warnings as Record<string, string> | undefined,
    module: normalizeMarketplaceModule(saved),
  };
}

export async function deleteMarketplaceModule(id: number | string) {
  await deleteModule(id);
  return { ok: true };
}

export async function createMarketplaceModuleRepo(
  moduleId: number | string,
  data: { repo_url: string; repo_branch?: string; gitea_repo?: string },
) {
  const res = await apiClient.post(`${BASE}/modules/${moduleId}/repo`, data);
  const repo = unwrapData(res);
  const mod = await getMarketplaceModule(moduleId);
  return { module: mod.module, repo };
}

export async function syncMarketplaceModuleRepo(moduleId: number | string) {
  const repo = await syncModuleRepo(moduleId);
  const mod = await getMarketplaceModule(moduleId);
  return { module: mod.module, repo };
}

export async function setMarketplaceModuleRepoVisibility(
  moduleId: number | string,
  isPrivate: boolean,
) {
  const res = await apiClient.patch(`${BASE}/modules/${moduleId}/repo`, { private: isPrivate });
  const repo = unwrapData(res);
  const mod = await getMarketplaceModule(moduleId);
  return { module: mod.module, repo };
}

export async function syncMarketplaceModuleReadme(moduleId: number | string) {
  const result = await syncModuleReadme(moduleId);
  const mod = await getMarketplaceModule(moduleId);
  return { module: mod.module, result };
}

export async function saveMarketplaceRelease(
  moduleId: number | string,
  data: Record<string, unknown> | FormData,
) {
  const payload: Record<string, unknown> = {};
  if (data instanceof FormData) {
    data.forEach((value, key) => {
      if (typeof value === 'string') payload[key] = value;
    });
  } else {
    Object.assign(payload, data);
  }
  return createModuleRelease(moduleId, {
    version: payload.version,
    changelog: payload.changelog,
    tag_name: payload.tag_name,
  });
}

export async function publishMarketplaceRelease(releaseId: number | string) {
  return publishRelease(releaseId);
}

export async function deleteMarketplaceRelease(releaseId: number | string) {
  return deleteRelease(releaseId);
}

export async function getMarketplaceCategories() {
  const res = await apiClient.get(`${BASE}/categories`, { params: { per_page: 100 } });
  const raw = unwrapData(res);
  const categories = listFromPayload(raw).map(normalizeCategory);
  return { categories };
}

export async function saveMarketplaceCategory(data: Partial<MarketplaceCategory>) {
  const payload = {
    name: data.name ?? '',
    slug: data.slug?.trim() || undefined,
    sort: data.sort,
    status: data.status,
  };
  if (data.id) {
    const res = await apiClient.put(`${BASE}/categories/${data.id}`, payload);
    return unwrapData(res);
  }
  const slug =
    payload.slug ||
    String(payload.name)
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '') ||
    `cat-${Date.now()}`;
  const res = await apiClient.post(`${BASE}/categories`, { ...payload, slug });
  return unwrapData(res);
}

export async function deleteMarketplaceCategory(id: number | string) {
  await apiClient.delete(`${BASE}/categories/${id}`);
  return { ok: true };
}

export async function getMarketplaceOrders() {
  const res = await apiClient.get(`${BASE}/orders`, { params: { per_page: 100, list: 1 } });
  const raw = unwrapData(res);
  const payload = asRecord(raw);
  const orderRows = Array.isArray(payload.orders)
    ? (payload.orders as Record<string, unknown>[])
    : listFromPayload(raw);
  const entitlementRows = Array.isArray(payload.entitlements)
    ? (payload.entitlements as Record<string, unknown>[])
    : [];
  const orders = orderRows.map(normalizeOrder);
  const entitlements = entitlementRows.map((row) => {
    const e = asRecord(row);
    const mod = asRecord(e.module);
    return {
      id: e.id,
      domain: e.domain,
      module_id: e.module_id,
      module_slug: mod.slug ?? e.module_slug,
      module_name: mod.name ?? e.module_name,
      status: e.status,
      installed_version: e.installed_version,
      expires_at: e.expires_at,
      order_id: e.order_id,
    };
  });
  return { orders, entitlements };
}

export async function getGiteaSettings() {
  const res = await apiClient.get(`${BASE}/gitea/settings`);
  const raw = unwrapData<Record<string, unknown> | null>(res);
  if (!raw) {
    return {
      settings: {
        provider: 'gitea' as OrgGitProvider,
        base_url: '',
        org: '',
        has_token: false,
        gitea_base_url: '',
        gitea_org: '',
        gitea_ip_override: '',
        gitea_ip_scheme: 'auto' as GiteaIpScheme,
        gitea_configured: false,
      } satisfies GiteaSettings,
    };
  }
  const base = String(raw.base_url ?? raw.host ?? '');
  const org = String(raw.org ?? '');
  const settings: GiteaSettings = {
    id: raw.id != null ? Number(raw.id) : undefined,
    provider: (String(raw.provider ?? 'gitea') as OrgGitProvider) || 'gitea',
    host: raw.host != null ? String(raw.host) : undefined,
    base_url: base,
    org,
    platform_source_id: raw.platform_source_id != null ? Number(raw.platform_source_id) : null,
    has_token: Boolean(raw.has_token),
    gitea_base_url: base,
    gitea_org: org,
    gitea_ip_override: '',
    gitea_ip_scheme: 'auto',
    gitea_configured: Boolean(raw.has_token),
  };
  return { settings };
}

export async function saveGiteaSettings(data: {
  provider?: OrgGitProvider;
  base_url?: string;
  host?: string;
  org?: string;
  token?: string;
  gitea_base_url?: string;
  gitea_org?: string;
  gitea_api_token?: string;
  gitea_ip_override?: string;
  gitea_ip_scheme?: GiteaIpScheme;
  platform_source_id?: number | null;
}) {
  const payload: Record<string, unknown> = {
    provider: data.provider ?? 'gitea',
    base_url: data.base_url ?? data.gitea_base_url ?? '',
    org: data.org ?? data.gitea_org ?? '',
  };
  if (payload.base_url) {
    payload.host = data.host || (typeof payload.base_url === 'string' ? payload.base_url : '');
  }
  const token = data.token ?? data.gitea_api_token;
  if (token && String(token).trim()) payload.token = String(token).trim();
  if (data.platform_source_id != null) payload.platform_source_id = data.platform_source_id;
  const res = await apiClient.put(`${BASE}/gitea/settings`, payload);
  return unwrapData(res);
}

export async function testGiteaConnectionDiagnostics(data?: {
  provider?: OrgGitProvider;
  gitea_base_url?: string;
  gitea_org?: string;
  gitea_ip_override?: string;
  gitea_ip_scheme?: GiteaIpScheme;
  gitea_api_token?: string;
  base_url?: string;
  org?: string;
  token?: string;
}): Promise<GiteaConnectionDiagnosticsResult> {
  try {
    const payload: Record<string, unknown> = {
      provider: data?.provider ?? 'gitea',
    };
    if (data?.base_url || data?.gitea_base_url) payload.base_url = data.base_url ?? data.gitea_base_url;
    if (data?.org || data?.gitea_org) payload.org = data.org ?? data.gitea_org;
    if (data?.token || data?.gitea_api_token) payload.token = data.token ?? data.gitea_api_token;
    const raw = await testGiteaConnection(payload);
    const rec = asRecord(raw);
    return {
      ok: Boolean(rec.ok ?? true),
      user: rec.user != null ? String(rec.user) : undefined,
      provider: rec.provider != null ? String(rec.provider) : undefined,
      hosts: Array.isArray(rec.hosts) ? (rec.hosts as string[]) : undefined,
      message: rec.message != null ? String(rec.message) : undefined,
      diag: asRecord(rec.diag) as GiteaConnectionDiagnosticsResult['diag'],
      steps: (rec.steps as GiteaConnectionDiagnosticsResult['steps']) ?? undefined,
      hints: Array.isArray(rec.hints) ? (rec.hints as string[]) : undefined,
    };
  } catch (err) {
    const message =
      err && typeof err === 'object' && 'response' in err
        ? String(
            (err as { response?: { data?: { message?: string; data?: GiteaConnectionDiagnosticsResult } } })
              .response?.data?.message ?? 'Connection failed',
          )
        : 'Connection failed';
    const nested =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { data?: GiteaConnectionDiagnosticsResult } } }).response?.data
            ?.data
        : undefined;
    if (nested) return { ...nested, ok: false, message: nested.message ?? message };
    return { ok: false, message };
  }
}

/* Basalam (CRM-parity endpoints under marketplace) */

export async function getBasalamOAuthStatus() {
  const res = await apiClient.get(`${BASE}/basalam/oauth/status`);
  return unwrapData<BasalamOAuthStatus>(res);
}

export async function saveBasalamOAuthConfig(payload: {
  client_id: string;
  client_secret?: string;
  redirect_uri: string;
  scopes: string;
}) {
  const res = await apiClient.post(`${BASE}/basalam/oauth/config`, payload);
  return unwrapData(res);
}

export async function listBasalamConnections() {
  const res = await apiClient.get(`${BASE}/basalam/connections`);
  const raw = unwrapData<unknown>(res);
  const rec = asRecord(raw);
  const connections = Array.isArray(rec.connections)
    ? (rec.connections as BasalamConnection[])
    : Array.isArray(raw)
      ? (raw as BasalamConnection[])
      : [];
  return { connections };
}

export async function disconnectBasalamConnection(siteUrl: string) {
  const res = await apiClient.post(`${BASE}/basalam/connections/disconnect`, { site_url: siteUrl });
  return unwrapData<{ connections?: BasalamConnection[] }>(res);
}
