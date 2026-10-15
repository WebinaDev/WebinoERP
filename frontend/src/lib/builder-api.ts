import apiClient from '@/lib/api-client'

type BuilderApiOptions = {
  method?: string
  json?: unknown
  body?: BodyInit | null
  headers?: Record<string, string>
  signal?: AbortSignal
}

/** Dashboard-compatible client: path may be `/api/v1/...`; ERP axios base already includes `/api`. */
export async function api<T>(path: string, opts: BuilderApiOptions = {}): Promise<T> {
  const url = path.replace(/^\/api(?=\/)/, '')
  const res = await apiClient.request({
    url,
    method: (opts.method ?? 'GET').toLowerCase(),
    data: opts.json ?? opts.body ?? undefined,
    headers: opts.body instanceof FormData ? { ...opts.headers, 'Content-Type': 'multipart/form-data' } : opts.headers,
    signal: opts.signal,
  })
  return res.data as T
}
