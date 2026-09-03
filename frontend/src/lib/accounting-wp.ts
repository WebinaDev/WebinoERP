import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

/**
 * POST /v1/accounting/wp-action/{action} — parity with WordPress `webinocrm_accounting_{action}`.
 */
export async function accountingWpAction<T = unknown>(
  action: string,
  body: Record<string, unknown> = {},
): Promise<T> {
  const res = await apiClient.post(`/v1/accounting/wp-action/${encodeURIComponent(action)}`, body);
  return unwrapData<T>(res);
}
