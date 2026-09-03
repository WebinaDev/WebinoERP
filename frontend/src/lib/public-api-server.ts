const API_BASE = process.env.INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost/api';

export type ApiServerOptions = {
  revalidate?: number | false;
  json?: unknown;
  method?: string;
};

export async function apiServer<T>(path: string, opts: ApiServerOptions = {}): Promise<T> {
  const init: RequestInit = {
    method: opts.method ?? (opts.json !== undefined ? 'POST' : 'GET'),
    headers: {
      Accept: 'application/json',
      ...(opts.json !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    ...(opts.json !== undefined ? { body: JSON.stringify(opts.json) } : {}),
  };

  if (opts.revalidate === false) {
    init.cache = 'no-store';
  } else if (typeof opts.revalidate === 'number') {
    init.next = { revalidate: opts.revalidate };
  } else {
    init.next = { revalidate: 60 };
  }

  const res = await fetch(`${API_BASE}${path}`, init);
  const text = await res.text();
  const data = text ? JSON.parse(text) : null;

  if (!res.ok) {
    const msg = typeof data?.message === 'string' ? data.message : `HTTP ${res.status}`;
    throw new Error(msg);
  }

  return data as T;
}

export async function getPublicSite() {
  return apiServer<{
    data: {
      name: string;
      logo_url?: string | null;
      favicon_url?: string | null;
      active_theme_slug?: string | null;
      branding?: Record<string, unknown> | null;
      nav?: unknown;
      social_links?: unknown;
    };
  }>('/v1/public/site');
}

export function siteHref(_locale?: string, path = ''): string {
  if (!path) return '/';
  return path.startsWith('/') ? path : `/${path}`;
}
