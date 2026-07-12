import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

export type MfgBom = Record<string, unknown> & { id?: number; lines?: Record<string, unknown>[] };
export type MfgWorkOrder = Record<string, unknown> & { id?: number };
export type MfgInspection = Record<string, unknown> & { id?: number };

export async function mfgOverview() {
  const res = await apiClient.get('/v1/mfg/overview');
  return unwrapData<Record<string, unknown>>(res);
}

export async function listBoms(params?: Record<string, unknown>) {
  const res = await apiClient.get('/v1/mfg/boms', { params });
  return unwrapData(res);
}

export async function createBom(body: Record<string, unknown>) {
  const res = await apiClient.post('/v1/mfg/boms', body);
  return unwrapData<MfgBom>(res);
}

export async function updateBom(id: number, body: Record<string, unknown>) {
  const res = await apiClient.put(`/v1/mfg/boms/${id}`, body);
  return unwrapData<MfgBom>(res);
}

export async function deleteBom(id: number) {
  await apiClient.delete(`/v1/mfg/boms/${id}`);
}

export async function listWorkOrders(params?: Record<string, unknown>) {
  const res = await apiClient.get('/v1/mfg/work-orders', { params });
  return unwrapData(res);
}

export async function createWorkOrder(body: Record<string, unknown>) {
  const res = await apiClient.post('/v1/mfg/work-orders', body);
  return unwrapData<MfgWorkOrder>(res);
}

export async function workOrderAction(id: number, action: 'release' | 'start' | 'complete' | 'cancel', body?: Record<string, unknown>) {
  const res = await apiClient.post(`/v1/mfg/work-orders/${id}/${action}`, body ?? {});
  return unwrapData<MfgWorkOrder>(res);
}

export async function listInspections(params?: Record<string, unknown>) {
  const res = await apiClient.get('/v1/mfg/inspections', { params });
  return unwrapData(res);
}

export async function createInspection(body: Record<string, unknown>) {
  const res = await apiClient.post('/v1/mfg/inspections', body);
  return unwrapData<MfgInspection>(res);
}

export async function completeInspection(id: number) {
  const res = await apiClient.post(`/v1/mfg/inspections/${id}/complete`);
  return unwrapData<MfgInspection>(res);
}

export async function mfgMrp(horizonDays = 30) {
  const res = await apiClient.get('/v1/mfg/planning/mrp', { params: { horizon_days: horizonDays } });
  return unwrapData<Record<string, unknown>>(res);
}
