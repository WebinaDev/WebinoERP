const INTERNAL_HOSTS = new Set([
  'localhost',
  '127.0.0.1',
  '::1',
  'backend',
  'erp-backend',
  'frontend',
  'erp-frontend',
  'nginx',
])

/**
 * Browser API base. Same-origin `/api` is what Caddy proxies to Laravel.
 * An absolute `http://localhost/api` (or docker DNS) baked into the client
 * bundle makes Axios throw "Network Error" on a remote ERP host.
 */
export function resolveApiBase(envValue?: string | null, pageOrigin?: string | null): string {
  const raw = (envValue ?? '').trim()
  if (!raw) return '/api'
  const normalized = raw.replace(/\/$/, '')
  if (normalized.startsWith('/')) return normalized

  if (!pageOrigin) return normalized

  try {
    const url = new URL(normalized)
    const page = new URL(pageOrigin)
    const host = url.hostname.toLowerCase()
    if (INTERNAL_HOSTS.has(host) && host !== page.hostname.toLowerCase()) {
      return '/api'
    }
    if (page.protocol === 'https:' && url.protocol === 'http:') {
      return '/api'
    }
  } catch {
    return '/api'
  }

  return normalized
}
