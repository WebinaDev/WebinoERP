/**
 * Generic fetch helper that wraps the axios apiClient.
 * Usage: apiFetch('settings', { method: 'POST', body: JSON.stringify(...) })
 * The path is relative to the API base URL (e.g. 'settings' → /api/v1/core/settings).
 */
import apiClient from '@/lib/api-client'

export async function apiFetch(
  path: string,
  init?: RequestInit,
): Promise<unknown> {
  const method = (init?.method ?? 'GET').toLowerCase() as
    | 'get'
    | 'post'
    | 'put'
    | 'patch'
    | 'delete'

  const headers: Record<string, string> = {}
  if (init?.headers) {
    const raw = init.headers as Record<string, string>
    Object.assign(headers, raw)
  }

  let data: unknown = undefined
  if (init?.body) {
    try {
      data = JSON.parse(init.body as string)
    } catch {
      data = init.body
    }
  }

  const res = await apiClient.request({
    method,
    url: path,
    headers,
    data,
  })

  return res.data
}
