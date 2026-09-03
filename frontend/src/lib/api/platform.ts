import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/platform';

export type PlatformServer = {
  id: number;
  name: string;
  ip: string;
  port?: number;
  user?: string;
  status?: string;
  is_localhost?: boolean;
  ssh_key_id?: number | null;
  proxy_type?: string | null;
  meta?: Record<string, unknown> | null;
  ssh_key?: { id: number; name: string; fingerprint?: string | null };
  created_at?: string;
};

export type PlatformSshKey = {
  id: number;
  name: string;
  fingerprint?: string | null;
  created_at?: string;
};

export type PlatformEnvironment = {
  id: number;
  project_id: number;
  name: string;
};

export type PlatformProject = {
  id: number;
  name: string;
  description?: string | null;
  crm_account_id?: number | null;
  environments?: PlatformEnvironment[];
  created_at?: string;
};

export type PlatformDomain = {
  id: number;
  resource_id: number;
  domain: string;
  force_https?: boolean;
  hsts?: boolean;
  redirect_to?: string | null;
  ssl_status?: string;
};

export type PlatformEnvVar = {
  id: number;
  resource_id: number;
  key: string;
  value?: string | null;
  is_secret?: boolean;
  is_buildtime?: boolean;
  is_runtime?: boolean;
  is_preview?: boolean;
};

export type PlatformVolume = {
  id: number;
  resource_id: number;
  name: string;
  mount_path: string;
  host_path?: string | null;
  is_file?: boolean;
};

export type PlatformResource = {
  id: number;
  uuid?: string;
  environment_id: number;
  server_id: number;
  destination_id?: number | null;
  type: string;
  name: string;
  status?: string;
  fqdn?: string | null;
  build_pack?: string | null;
  git_repository?: string | null;
  git_branch?: string | null;
  dockerfile_location?: string | null;
  docker_compose_location?: string | null;
  docker_compose_raw?: string | null;
  docker_image?: string | null;
  database_type?: string | null;
  service_template?: string | null;
  site_type_slug?: string | null;
  crm_account_id?: number | null;
  ports_exposes?: number | null;
  settings?: Record<string, unknown> | null;
  meta?: Record<string, unknown> | null;
  domains?: PlatformDomain[];
  env_vars?: PlatformEnvVar[];
  envVars?: PlatformEnvVar[];
  volumes?: PlatformVolume[];
  created_at?: string;
};

export type PlatformDeployment = {
  id: number;
  resource_id: number;
  status: string;
  log?: string | null;
  logs?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  created_at?: string;
};

export type PlatformStorage = {
  id: number;
  uuid?: string;
  name: string;
  driver: string;
  endpoint?: string | null;
  bucket?: string | null;
  region?: string | null;
  path_style?: boolean;
  created_at?: string;
};

export type PlatformBackup = {
  id: number;
  resource_id: number;
  storage_id?: number | null;
  status: string;
  path?: string | null;
  created_at?: string;
};

export type PlatformBackupSchedule = {
  id: number;
  resource_id: number;
  storage_id?: number | null;
  cron: string;
  retention_days?: number;
  enabled?: boolean;
};

export type PlatformSource = {
  id: number;
  uuid?: string;
  name: string;
  provider: string;
  base_url?: string | null;
  created_at?: string;
};

export type PlatformNotificationChannel = {
  id: number;
  name: string;
  type: string;
  config: Record<string, unknown>;
  enabled?: boolean;
};

export type PlatformServiceTemplate = {
  id: number;
  slug: string;
  name: string;
  category?: string;
  description?: string | null;
  compose?: string | null;
  meta?: Record<string, unknown> | null;
};

export type PlatformServerImage = {
  ref: string;
  id: string;
  size: string;
};

export type PlatformResourceWebhook = {
  token: string;
  url: string;
};

export type PlatformSharedVariable = {
  id: number;
  key: string;
  value?: string | null;
  is_secret?: boolean;
  project_id?: number | null;
};

export type PlatformTag = {
  id: number;
  name: string;
  color?: string | null;
};

export type PlatformSettings = {
  default_proxy: string;
  wildcard_domain?: string | null;
  api_enabled: boolean;
};

export type PlatformDashboard = {
  servers: number;
  projects: number;
  resources: number;
  running: number;
  recent_deployments: PlatformDeployment[];
  servers_list: PlatformServer[];
  projects_list: PlatformProject[];
};

export type CrmPlatformSites = {
  resources: PlatformResource[];
  provisions: Record<string, unknown>[];
};

export type Paginated<T> = {
  data: T[];
  current_page?: number;
  last_page?: number;
  total?: number;
};

function asList<T>(raw: T[] | Paginated<T> | { data?: T[] }): T[] {
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === 'object' && 'data' in raw && Array.isArray(raw.data)) return raw.data;
  return [];
}

export async function fetchPlatformDashboard() {
  const res = await apiClient.get(`${BASE}/dashboard`);
  return unwrapData<PlatformDashboard>(res);
}

export async function fetchServers() {
  const res = await apiClient.get(`${BASE}/servers`);
  return unwrapData<PlatformServer[]>(res);
}

export async function fetchServer(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}`);
  return unwrapData<PlatformServer>(res);
}

export async function createServer(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/servers`, body);
  return unwrapData<PlatformServer>(res);
}

export async function updateServer(id: number | string, body: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/servers/${id}`, body);
  return unwrapData<PlatformServer>(res);
}

export async function deleteServer(id: number | string) {
  const res = await apiClient.delete(`${BASE}/servers/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function validateServer(id: number | string) {
  const res = await apiClient.post(`${BASE}/servers/${id}/validate`);
  return unwrapData<{ server: PlatformServer; result: unknown }>(res);
}

export async function bootstrapServer(id: number | string) {
  const res = await apiClient.post(`${BASE}/servers/${id}/bootstrap`);
  return unwrapData<{ server: PlatformServer; result: unknown }>(res);
}

export async function fetchServerResources(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}/resources`);
  return unwrapData<unknown[]>(res);
}

export async function fetchServerImages(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}/images`);
  return unwrapData<PlatformServerImage[]>(res);
}

export async function pullServerImage(serverId: number | string, ref: string) {
  const res = await apiClient.post(`${BASE}/servers/${serverId}/images/pull`, { ref });
  return unwrapData<unknown>(res);
}

export async function deleteServerImage(serverId: number | string, ref: string) {
  const res = await apiClient.post(`${BASE}/servers/${serverId}/images/delete`, { ref });
  return unwrapData<unknown>(res);
}

export async function serverContainerAction(serverId: number | string, container: string, action: string) {
  const res = await apiClient.post(`${BASE}/servers/${serverId}/containers/${encodeURIComponent(container)}`, { action });
  return unwrapData<unknown>(res);
}

export async function fetchServerContainerLogs(serverId: number | string, container: string) {
  const res = await apiClient.get(`${BASE}/servers/${serverId}/containers/${encodeURIComponent(container)}/logs`);
  return unwrapData<{ logs: string }>(res);
}

export async function fetchServerNetworks(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}/networks`);
  return unwrapData<unknown[]>(res);
}

export async function createServerNetwork(id: number | string, name: string) {
  const res = await apiClient.post(`${BASE}/servers/${id}/networks`, { name });
  return unwrapData<unknown>(res);
}

export async function fetchServerMetrics(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}/metrics`);
  return unwrapData<unknown>(res);
}

export async function cleanupServer(id: number | string) {
  const res = await apiClient.post(`${BASE}/servers/${id}/cleanup`);
  return unwrapData<unknown>(res);
}

export async function fetchServerProxy(id: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${id}/proxy`);
  return unwrapData<{ raw: string; exit_code: number }>(res);
}

export async function execServerTerminal(id: number | string, command: string) {
  const res = await apiClient.post(`${BASE}/servers/${id}/terminal`, { command });
  return unwrapData<{ stdout?: string; stderr?: string; exit_code?: number }>(res);
}

export async function fetchSshKeys() {
  const res = await apiClient.get(`${BASE}/ssh-keys`);
  return unwrapData<PlatformSshKey[]>(res);
}

export async function createSshKey(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/ssh-keys`, body);
  return unwrapData<PlatformSshKey>(res);
}

export async function deleteSshKey(id: number | string) {
  const res = await apiClient.delete(`${BASE}/ssh-keys/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchProjects() {
  const res = await apiClient.get(`${BASE}/projects`);
  return unwrapData<PlatformProject[]>(res);
}

export async function fetchProject(id: number | string) {
  const res = await apiClient.get(`${BASE}/projects/${id}`);
  return unwrapData<PlatformProject>(res);
}

export async function createProject(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/projects`, body);
  return unwrapData<PlatformProject>(res);
}

export async function updateProject(id: number | string, body: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/projects/${id}`, body);
  return unwrapData<PlatformProject>(res);
}

export async function deleteProject(id: number | string) {
  const res = await apiClient.delete(`${BASE}/projects/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function createProjectEnvironment(projectId: number | string, name: string) {
  const res = await apiClient.post(`${BASE}/projects/${projectId}/environments`, { name });
  return unwrapData<PlatformEnvironment>(res);
}

export async function fetchResources(params?: { environment_id?: number; type?: string }) {
  const res = await apiClient.get(`${BASE}/resources`, { params });
  return asList<PlatformResource>(unwrapData<Paginated<PlatformResource> | PlatformResource[]>(res));
}

export async function fetchResource(id: number | string) {
  const res = await apiClient.get(`${BASE}/resources/${id}`);
  return unwrapData<PlatformResource>(res);
}

export async function createResource(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/resources`, body);
  return unwrapData<PlatformResource>(res);
}

export async function updateResource(id: number | string, body: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/resources/${id}`, body);
  return unwrapData<PlatformResource>(res);
}

export async function deleteResource(id: number | string) {
  const res = await apiClient.delete(`${BASE}/resources/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function deployResource(id: number | string) {
  const res = await apiClient.post(`${BASE}/resources/${id}/deploy`);
  return unwrapData<PlatformDeployment>(res);
}

export async function fetchResourceDeployments(id: number | string) {
  const res = await apiClient.get(`${BASE}/resources/${id}/deployments`);
  return unwrapData<PlatformDeployment[]>(res);
}

export async function syncResourceEnv(id: number | string, vars: Record<string, unknown>[]) {
  const res = await apiClient.put(`${BASE}/resources/${id}/env`, { vars });
  return unwrapData<PlatformEnvVar[]>(res);
}

export async function syncResourceVolumes(id: number | string, volumes: Record<string, unknown>[]) {
  const res = await apiClient.put(`${BASE}/resources/${id}/volumes`, { volumes });
  return unwrapData<PlatformVolume[]>(res);
}

export async function startResource(id: number | string) {
  const res = await apiClient.post(`${BASE}/resources/${id}/start`);
  return unwrapData<unknown>(res);
}

export async function stopResource(id: number | string) {
  const res = await apiClient.post(`${BASE}/resources/${id}/stop`);
  return unwrapData<unknown>(res);
}

export async function addResourceDomain(resourceId: number | string, body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/resources/${resourceId}/domains`, body);
  return unwrapData<PlatformDomain>(res);
}

export async function deleteDomain(id: number | string) {
  const res = await apiClient.delete(`${BASE}/domains/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function refreshDomainSsl(domainId: number | string) {
  const res = await apiClient.post(`${BASE}/domains/${domainId}/ssl/refresh`);
  return unwrapData<PlatformDomain>(res);
}

export async function cloneResource(id: number | string, body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/resources/${id}/clone`, body);
  return unwrapData<PlatformResource>(res);
}

export async function moveResource(id: number | string, body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/resources/${id}/move`, body);
  return unwrapData<PlatformResource>(res);
}

export async function ensureResourceWebhook(id: number | string) {
  const res = await apiClient.post(`${BASE}/resources/${id}/webhook`);
  return unwrapData<PlatformResourceWebhook>(res);
}

export async function fetchStorages() {
  const res = await apiClient.get(`${BASE}/storages`);
  return unwrapData<PlatformStorage[]>(res);
}

export async function createStorage(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/storages`, body);
  return unwrapData<PlatformStorage>(res);
}

export async function deleteStorage(id: number | string) {
  const res = await apiClient.delete(`${BASE}/storages/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchBackups() {
  const res = await apiClient.get(`${BASE}/backups`);
  return unwrapData<PlatformBackup[]>(res);
}

export async function fetchBackupSchedules() {
  const res = await apiClient.get(`${BASE}/backup-schedules`);
  return unwrapData<PlatformBackupSchedule[]>(res);
}

export async function createBackupSchedule(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/backup-schedules`, body);
  return unwrapData<PlatformBackupSchedule>(res);
}

export async function runBackup(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/backups/run`, body);
  return unwrapData<PlatformBackup>(res);
}

export async function restoreBackup(backupId: number | string) {
  const res = await apiClient.post(`${BASE}/backups/${backupId}/restore`);
  return unwrapData<PlatformBackup>(res);
}

export async function fetchSources() {
  const res = await apiClient.get(`${BASE}/sources`);
  return unwrapData<PlatformSource[]>(res);
}

export async function createSource(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/sources`, body);
  return unwrapData<PlatformSource>(res);
}

export async function deleteSource(id: number | string) {
  const res = await apiClient.delete(`${BASE}/sources/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchNotifications() {
  const res = await apiClient.get(`${BASE}/notifications`);
  return unwrapData<PlatformNotificationChannel[]>(res);
}

export async function createNotification(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/notifications`, body);
  return unwrapData<PlatformNotificationChannel>(res);
}

export async function testNotification(id: number | string) {
  const res = await apiClient.post(`${BASE}/notifications/${id}/test`);
  return unwrapData<{ sent: boolean }>(res);
}

export async function deleteNotification(id: number | string) {
  const res = await apiClient.delete(`${BASE}/notifications/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchServiceTemplates(category?: string) {
  const res = await apiClient.get(`${BASE}/services/templates`, { params: category ? { category } : undefined });
  return unwrapData<PlatformServiceTemplate[]>(res);
}

export async function fetchServiceTemplate(slug: string) {
  const res = await apiClient.get(`${BASE}/services/templates/${slug}`);
  return unwrapData<PlatformServiceTemplate>(res);
}

export async function fetchPlatformSettings() {
  const res = await apiClient.get(`${BASE}/settings`);
  return unwrapData<PlatformSettings>(res);
}

export async function updatePlatformSettings(body: Record<string, unknown>) {
  const res = await apiClient.put(`${BASE}/settings`, body);
  return unwrapData<PlatformSettings>(res);
}

export async function fetchSharedVariables(projectId?: number) {
  const res = await apiClient.get(`${BASE}/variables`, { params: projectId ? { project_id: projectId } : undefined });
  return unwrapData<PlatformSharedVariable[]>(res);
}

export async function createSharedVariable(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/variables`, body);
  return unwrapData<PlatformSharedVariable>(res);
}

export async function deleteSharedVariable(id: number | string) {
  const res = await apiClient.delete(`${BASE}/variables/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchTags() {
  const res = await apiClient.get(`${BASE}/tags`);
  return unwrapData<PlatformTag[]>(res);
}

export async function createTag(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/tags`, body);
  return unwrapData<PlatformTag>(res);
}

export async function launchWebino(body: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/webino/launch`, body);
  return unwrapData<unknown>(res);
}

export async function fetchCrmSites(accountId: number | string) {
  const res = await apiClient.get(`${BASE}/crm/${accountId}/sites`);
  return unwrapData<CrmPlatformSites>(res);
}

export type PlatformApiToken = {
  id: number;
  name: string;
  abilities?: string[];
  expires_at?: string | null;
  last_used_at?: string | null;
  created_at?: string;
};

export async function fetchPlatformTokens() {
  const res = await apiClient.get(`${BASE}/tokens`);
  return unwrapData<PlatformApiToken[]>(res);
}

export async function createPlatformToken(body: { name: string; abilities: string[]; expires_at?: string }) {
  const res = await apiClient.post(`${BASE}/tokens`, body);
  return unwrapData<{ token: PlatformApiToken; plain_token: string }>(res);
}

export async function deletePlatformToken(id: number | string) {
  const res = await apiClient.delete(`${BASE}/tokens/${id}`);
  return unwrapData<{ deleted: boolean }>(res);
}

export async function fetchDestinations(serverId: number | string) {
  const res = await apiClient.get(`${BASE}/servers/${serverId}/destinations`);
  return unwrapData<Array<{ id: number; name: string; network_name: string; driver?: string }>>(res);
}

export async function reloadProxy(serverId: number | string) {
  const res = await apiClient.post(`${BASE}/servers/${serverId}/proxy/reload`);
  return unwrapData<unknown>(res);
}
