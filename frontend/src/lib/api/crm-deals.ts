import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/crm';

export async function listDeals(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/deals`, { params });
  return unwrapData(res);
}

export async function saveDeal(data: Record<string, unknown>, id?: number) {
  if (id) {
    const res = await apiClient.put(`${BASE}/deals/${id}`, data);
    return unwrapData(res);
  }
  const res = await apiClient.post(`${BASE}/deals`, data);
  return unwrapData(res);
}

export async function moveDeal(id: number | string, stageId: number) {
  const res = await apiClient.patch(`${BASE}/deals/${id}/move`, { stage_id: stageId });
  return unwrapData(res);
}

export async function listPipelines(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/pipelines`, { params });
  return unwrapData(res);
}

export async function savePipeline(data: Record<string, unknown>, id?: number) {
  if (id) {
    const res = await apiClient.put(`${BASE}/pipelines/${id}`, data);
    return unwrapData(res);
  }
  const res = await apiClient.post(`${BASE}/pipelines`, data);
  return unwrapData(res);
}

export async function getPipelineKanban(id: number | string) {
  const res = await apiClient.get(`${BASE}/pipelines/${id}/kanban`);
  return unwrapData(res);
}

export async function savePipelineStage(pipelineId: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/pipelines/${pipelineId}/stages`, data);
  return unwrapData(res);
}

export async function deletePipelineStage(pipelineId: number | string, stageId: number | string) {
  const res = await apiClient.delete(`${BASE}/pipelines/${pipelineId}/stages/${stageId}`);
  return unwrapData(res);
}
