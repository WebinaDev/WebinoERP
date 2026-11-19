import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/core';

export type UserType = 'staff' | 'customer' | 'other';

export type ManagedUser = {
  id: number;
  name: string;
  first_name?: string | null;
  last_name?: string | null;
  username?: string | null;
  email: string;
  phone?: string | null;
  avatar_url?: string | null;
  is_active: boolean;
  locked: boolean;
  locked_until?: string | null;
  must_change_password: boolean;
  last_login_at?: string | null;
  created_at?: string | null;
  roles: string[];
  type: UserType;
  employee_id?: number | null;
};

export type UserProfile = {
  national_id?: string | null;
  birth_date?: string | null;
  gender?: string | null;
  father_name?: string | null;
  phone_alt?: string | null;
  landline?: string | null;
  province?: string | null;
  city?: string | null;
  address?: string | null;
  postal_code?: string | null;
  job_title?: string | null;
  company?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  bio?: string | null;
  admin_notes?: string | null;
};

export type UserDetail = ManagedUser & {
  lock_reason?: string | null;
  password_changed_at?: string | null;
  last_login_ip?: string | null;
  email_verified_at?: string | null;
  two_factor: { required: boolean; setting_enabled: boolean; has_phone: boolean };
  permissions: string[];
  direct_permissions: string[];
  profile: UserProfile;
  employee?: { id: number; employee_code?: string; department?: string | null; position?: string | null; status?: string } | null;
  crm_accounts: Array<{ id: number; name: string; is_primary?: boolean }>;
  documents_count: number;
  sessions_count: number;
};

export type DirectoryStats = { total: number; active: number; inactive: number; locked: number; staff: number; customers: number };

export type DirectoryResult = {
  rows: ManagedUser[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; stats?: DirectoryStats };
};

export type UsersMeta = {
  roles: Array<{ name: string; label: string }>;
  document_types: string[];
  notification_channels: string[];
  notification_categories: string[];
  locked_categories: string[];
};

export type NotificationMatrix = {
  channels: Record<string, boolean>;
  categories: Record<string, { enabled: boolean; locked: boolean; channels: Record<string, boolean> }>;
  quiet_hours: { start: string | null; end: string | null };
};

export type UserDocument = {
  id: number;
  user_id: number;
  type: string;
  title: string;
  number?: string | null;
  issued_at?: string | null;
  expires_at?: string | null;
  notes?: string | null;
  original_name: string;
  mime_type?: string | null;
  size_bytes: number;
  version: number;
  status: string;
  is_expired: boolean;
  expires_soon: boolean;
  created_at?: string;
  updated_at?: string;
  uploader?: { id: number; name: string } | null;
};

export type UserSession = {
  id: number;
  name: string;
  device_name?: string | null;
  ip?: string | null;
  user_agent?: string | null;
  last_used_at?: string | null;
  last_activity_at?: string | null;
  created_at?: string | null;
};

export type ActivityRow = {
  id: number;
  action: string;
  actor?: { id: number; name: string } | null;
  about_user?: boolean;
  subject_type?: string;
  meta?: Record<string, unknown> | null;
  created_at?: string | null;
};

export async function getUsersDirectory(params: Record<string, unknown>): Promise<DirectoryResult> {
  const res = await apiClient.get(`${BASE}/users/directory`, { params });
  const body = res.data as { data?: ManagedUser[]; meta?: DirectoryResult['meta'] };
  return {
    rows: Array.isArray(body?.data) ? body.data : [],
    meta: body?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0 },
  };
}

export async function getUsersMeta() {
  return unwrapData<UsersMeta>(await apiClient.get(`${BASE}/users/meta`));
}

export async function createUser(payload: Record<string, unknown>) {
  return unwrapData<{ user: ManagedUser; temporary_password: string | null }>(await apiClient.post(`${BASE}/users/accounts`, payload));
}

export async function getUser(id: number | string) {
  return unwrapData<UserDetail>(await apiClient.get(`${BASE}/users/${id}`));
}

export async function updateUserAccount(id: number | string, payload: Record<string, unknown>) {
  return unwrapData<UserDetail>(await apiClient.patch(`${BASE}/users/${id}/account`, payload));
}

export async function setUserPassword(id: number | string, payload: Record<string, unknown>) {
  return unwrapData<{ temporary_password: string | null; must_change_password: boolean; revoked_sessions: number }>(
    await apiClient.post(`${BASE}/users/${id}/password`, payload),
  );
}

export async function lockUser(id: number | string, payload: { locked: boolean; until?: string | null; reason?: string | null }) {
  return unwrapData<UserDetail>(await apiClient.post(`${BASE}/users/${id}/lock`, payload));
}

export async function setUserRoles(id: number | string, roles: string[]) {
  return unwrapData<UserDetail>(await apiClient.put(`${BASE}/users/${id}/roles`, { roles }));
}

export async function updateUserProfile(id: number | string, payload: UserProfile) {
  return unwrapData<UserProfile>(await apiClient.put(`${BASE}/users/${id}/profile`, payload));
}

export async function getUserSessions(id: number | string) {
  return unwrapData<UserSession[]>(await apiClient.get(`${BASE}/users/${id}/sessions`));
}

export async function revokeUserSession(id: number | string, tokenId: number) {
  await apiClient.delete(`${BASE}/users/${id}/sessions/${tokenId}`);
}

export async function revokeAllUserSessions(id: number | string) {
  await apiClient.delete(`${BASE}/users/${id}/sessions`);
}

export async function getUserActivity(id: number | string) {
  return unwrapData<ActivityRow[]>(await apiClient.get(`${BASE}/users/${id}/activity`));
}

/** `scope` is a user id for admins, or "me" for self-service. */
function prefsUrl(scope: number | string | 'me') {
  return scope === 'me' ? `${BASE}/me/notification-preferences` : `${BASE}/users/${scope}/notification-preferences`;
}

export async function getNotificationMatrix(scope: number | string | 'me') {
  return unwrapData<NotificationMatrix>(await apiClient.get(prefsUrl(scope)));
}

export async function saveNotificationMatrix(scope: number | string | 'me', payload: Partial<NotificationMatrix>) {
  return unwrapData<NotificationMatrix>(await apiClient.put(prefsUrl(scope), payload));
}

function docsUrl(scope: number | string | 'me') {
  return scope === 'me' ? `${BASE}/me/documents` : `${BASE}/users/${scope}/documents`;
}

export async function listDocuments(scope: number | string | 'me') {
  return unwrapData<UserDocument[]>(await apiClient.get(docsUrl(scope)));
}

export async function uploadDocument(scope: number | string | 'me', form: FormData) {
  return unwrapData<UserDocument>(
    await apiClient.post(docsUrl(scope), form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120_000 }),
  );
}

export async function updateDocument(scope: number | string | 'me', docId: number, form: FormData) {
  return unwrapData<UserDocument>(
    await apiClient.post(`${docsUrl(scope)}/${docId}`, form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120_000 }),
  );
}

export async function deleteDocument(scope: number | string | 'me', docId: number) {
  await apiClient.delete(`${docsUrl(scope)}/${docId}`);
}

export async function downloadDocument(scope: number | string | 'me', doc: UserDocument) {
  const res = await apiClient.get(`${docsUrl(scope)}/${doc.id}/download`, { responseType: 'blob', timeout: 120_000 });
  const blob = res.data instanceof Blob ? res.data : new Blob([res.data as BlobPart], { type: doc.mime_type ?? 'application/octet-stream' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = doc.original_name || `document-${doc.id}`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

export async function getMyAccount() {
  return unwrapData<ManagedUser & { password_changed_at?: string | null; profile: UserProfile }>(await apiClient.get(`${BASE}/me`));
}

export async function updateMyAccount(payload: Record<string, unknown>) {
  return unwrapData<ManagedUser & { profile: UserProfile }>(await apiClient.patch(`${BASE}/me`, payload));
}

export async function changeMyPassword(payload: { current_password: string; password: string; password_confirmation: string }) {
  await apiClient.post(`${BASE}/auth/password/set`, payload);
}

export async function getMySessions() {
  return unwrapData<UserSession[]>(await apiClient.get(`${BASE}/me/sessions`));
}

export async function revokeMySession(id: number) {
  await apiClient.delete(`${BASE}/me/sessions/${id}`);
}

export async function revokeMyOtherSessions() {
  await apiClient.delete(`${BASE}/me/sessions/others`);
}
