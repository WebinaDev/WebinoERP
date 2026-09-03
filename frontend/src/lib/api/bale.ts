import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

export type BaleSettings = Record<string, unknown>;

export type BaleCampaign = {
  id?: number;
  name?: string;
  status?: string;
  message?: string;
};

export async function baleGetSettings(): Promise<BaleSettings> {
  const res = await apiClient.get('webinocrm/v1/bale/settings');
  return unwrapData<BaleSettings>(res);
}

export async function baleUpdateSettings(body: BaleSettings): Promise<BaleSettings> {
  const res = await apiClient.post('webinocrm/v1/bale/settings', body);
  return unwrapData<BaleSettings>(res);
}

export async function baleListCampaigns(): Promise<BaleCampaign[]> {
  const res = await apiClient.get('webinocrm/v1/bale/campaigns');
  const body = unwrapData<{ campaigns?: BaleCampaign[] }>(res);
  return Array.isArray(body.campaigns) ? body.campaigns : [];
}

export async function baleCreateCampaign(payload: { name: string; message: string }): Promise<number | null> {
  const res = await apiClient.post('webinocrm/v1/bale/campaigns', payload);
  const body = unwrapData<{ id?: number }>(res);
  return body.id ?? null;
}

export async function baleRunCampaign(id: number): Promise<unknown> {
  const res = await apiClient.post(`webinocrm/v1/bale/campaigns/${id}/run`);
  return unwrapData(res);
}

export async function baleGetUserLogs(chatId: string): Promise<unknown[]> {
  const res = await apiClient.get('webinocrm/v1/bale/user-logs', { params: { chat_id: chatId, limit: 50 } });
  const body = unwrapData<{ logs?: unknown[] }>(res);
  return Array.isArray(body.logs) ? body.logs : [];
}

export async function baleSendBulk(message: string): Promise<unknown> {
  const res = await apiClient.post('webinocrm/v1/bale/message/bulk', { message, mode: 'all' });
  return unwrapData(res);
}
