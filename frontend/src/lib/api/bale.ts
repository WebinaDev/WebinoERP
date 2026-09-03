import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

export type BaleSettings = Record<string, unknown>;

export type BaleCampaign = {
  id?: number;
  name?: string;
  status?: string;
  message?: string;
  message_template?: string;
  segment_key?: string;
  variant?: string;
};

export type BaleBotStats = {
  total_events: number;
  total_logs: number;
  total_users: number;
  total_businesses: number;
  started_users: number;
};

export type BaleKpi = {
  start_to_lead_rate: number;
  lead_to_customer_rate: number;
  first_response_minutes: number;
  retention_rate: number;
  campaign_revenue_impact: number;
  funnel_dropoff: Record<string, number>;
  campaign_metrics?: Record<string, number>;
};

export type BaleLogRow = {
  id?: number | string;
  level?: string;
  log_type?: string;
  context?: unknown;
  created_at?: string;
};

export async function baleGetSettings(): Promise<BaleSettings> {
  const res = await apiClient.get('webinocrm/v1/bale/settings');
  return unwrapData<BaleSettings>(res);
}

export async function baleUpdateSettings(body: BaleSettings): Promise<BaleSettings> {
  const res = await apiClient.post('webinocrm/v1/bale/settings', body);
  return unwrapData<BaleSettings>(res);
}

export async function baleFetchWebhookUrl(): Promise<{ url: string; message?: string }> {
  const res = await apiClient.get('webinocrm/v1/bale/webhook-url');
  const body = unwrapData<{ url?: string; message?: string }>(res);
  return { url: typeof body.url === 'string' ? body.url : '', message: body.message };
}

export async function baleSetWebhook(): Promise<unknown> {
  const res = await apiClient.post('webinocrm/v1/bale/set-webhook', {});
  return unwrapData(res);
}

export async function baleWebhookInfo(): Promise<unknown> {
  const res = await apiClient.post('webinocrm/v1/bale/diagnostics/webhook-info', {});
  const body = unwrapData<{ webhook_info?: unknown }>(res);
  return body.webhook_info ?? body;
}

export async function baleTestLog(): Promise<unknown> {
  const res = await apiClient.post('webinocrm/v1/bale/diagnostics/test-log', {});
  return unwrapData(res);
}

export async function baleDiagnosticsStats(): Promise<{ support_opened: number; support_item_clicked: number }> {
  const res = await apiClient.get('webinocrm/v1/bale/diagnostics/stats');
  return unwrapData(res);
}

export async function baleGetStats(): Promise<BaleBotStats> {
  const res = await apiClient.get('webinocrm/v1/bale/stats');
  return unwrapData<BaleBotStats>(res);
}

export async function baleGetKpi(): Promise<BaleKpi> {
  const res = await apiClient.get('webinocrm/v1/bale/kpi');
  return unwrapData<BaleKpi>(res);
}

export async function baleGetLogs(limit = 80): Promise<BaleLogRow[]> {
  const res = await apiClient.get('webinocrm/v1/bale/logs', { params: { limit } });
  const body = unwrapData<{ logs?: BaleLogRow[] }>(res);
  return Array.isArray(body.logs) ? body.logs : [];
}

export async function baleListCampaigns(): Promise<BaleCampaign[]> {
  const res = await apiClient.get('webinocrm/v1/bale/campaigns');
  const body = unwrapData<{ campaigns?: BaleCampaign[] }>(res);
  return Array.isArray(body.campaigns) ? body.campaigns : [];
}

export async function baleCreateCampaign(payload: {
  name: string;
  message?: string;
  message_template?: string;
  segment_key?: string;
  variant?: string;
}): Promise<number | null> {
  const res = await apiClient.post('webinocrm/v1/bale/campaigns', {
    name: payload.name,
    message_template: payload.message_template ?? payload.message ?? '',
    segment_key: payload.segment_key ?? 'newcomer',
    variant: payload.variant ?? 'A',
  });
  const body = unwrapData<{ id?: number }>(res);
  return body.id ?? null;
}

export async function baleRunCampaign(id: number): Promise<unknown> {
  const res = await apiClient.post(`webinocrm/v1/bale/campaigns/${id}/run`);
  return unwrapData(res);
}

export async function baleGetUserLogs(chatId: string): Promise<{
  events: Array<Record<string, unknown>>;
  logs: Array<Record<string, unknown>>;
}> {
  const res = await apiClient.get('webinocrm/v1/bale/user-logs', { params: { chat_id: chatId, limit: 80 } });
  const body = unwrapData<{
    events?: Array<Record<string, unknown>>;
    logs?: Array<Record<string, unknown>>;
  }>(res);
  return {
    events: Array.isArray(body.events) ? body.events : [],
    logs: Array.isArray(body.logs) ? body.logs : [],
  };
}

export async function baleSendBulk(message: string): Promise<unknown> {
  const res = await apiClient.post('webinocrm/v1/bale/message/bulk', { message, mode: 'all' });
  return unwrapData(res);
}
