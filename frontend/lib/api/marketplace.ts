import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/marketplace';

export async function listModules(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/modules`, { params });
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
