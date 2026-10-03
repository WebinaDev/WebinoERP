import apiClient from '@/lib/api-client'

type BuilderApiOptions = {
  method?: string
  json?: unknown
  headers?: Record<string, string>
}

/** Dashboard-compatible client: path may be `/api/v1/...`; ERP axios base already includes `/api`. */
export async function api<T>(path: string, opts: BuilderApiOptions = {}): Promise<T> {
  const url = path.replace(/^\/api(?=\/)/, '')
  const res = await apiClient.request({
    url,
    method: (opts.method ?? 'GET').toLowerCase(),
    data: opts.json,
    headers: opts.headers,
  })
  return res.data as T
}
