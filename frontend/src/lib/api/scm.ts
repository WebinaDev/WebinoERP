import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/scm';

export async function listWarehouses(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/warehouses`, { params });
  return unwrapData(res);
}

export async function saveWarehouse(id: number | null, data: Record<string, unknown>) {
  if (id) {
    const res = await apiClient.post(`${BASE}/warehouses/${id}`, data);
    return unwrapData(res);
  }
  const res = await apiClient.post(`${BASE}/warehouses`, data);
  return unwrapData(res);
}

export async function deleteWarehouse(id: number) {
  const res = await apiClient.delete(`${BASE}/warehouses/${id}`);
  return unwrapData(res);
}

export async function getStock(warehouseId: number | string, productId: number | string) {
  const res = await apiClient.get(`${BASE}/stock/${warehouseId}/${productId}`);
  return unwrapData(res);
}

export async function createInbound(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/inbound/create`, data);
  return unwrapData(res);
}

export async function postInbound(id: number | string) {
  const res = await apiClient.post(`${BASE}/inbound/post`, { id });
  return unwrapData(res);
}

export async function getInbound(id: number | string) {
  const res = await apiClient.get(`${BASE}/inbound/${id}`);
  return unwrapData(res);
}

export async function createOutbound(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/outbound/create`, data);
  return unwrapData(res);
}

export async function postOutbound(id: number | string) {
  const res = await apiClient.post(`${BASE}/outbound/post`, { id });
  return unwrapData(res);
}

export async function createAudit(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/audit/create`, data);
  return unwrapData(res);
}

export async function completeAudit(id: number | string) {
  const res = await apiClient.post(`${BASE}/audit/complete`, { id });
  return unwrapData(res);
}

export async function postAudit(id: number | string) {
  const res = await apiClient.post(`${BASE}/audit/post`, { id });
  return unwrapData(res);
}
