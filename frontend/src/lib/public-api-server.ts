function apiRoot(): string {
  const raw = (
    process.env.INTERNAL_API_URL ??
    process.env.NEXT_PUBLIC_API_URL ??
    'http://backend:8080'
  ).replace(/\/$/, '');
  if (raw.endsWith('/api')) {
    return raw;
  }
  return `${raw}/api`;
}

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

  const url = `${apiRoot()}${path.startsWith('/') ? path : `/${path}`}`;
  const res = await fetch(url, init);
  const text = await res.text();
  let data: unknown = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error(`Non-JSON response from ${url} (HTTP ${res.status})`);
    }
  }

  if (!res.ok) {
    const msg =
      data && typeof data === 'object' && 'message' in data && typeof (data as { message: unknown }).message === 'string'
        ? (data as { message: string }).message
        : `HTTP ${res.status}`;
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
