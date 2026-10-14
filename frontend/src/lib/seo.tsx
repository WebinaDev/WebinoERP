import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { apiServer, getPublicSite } from '@/lib/public-api-server';

/** Editable per-item / per-route SEO (stored as data: entity `seo` column, page meta.seo or branding.seo_routes). */
export type Seo = { title?: string | null; description?: string | null; og_image?: string | null; canonical?: string | null; noindex?: boolean | null };

const plain = (s?: string | null, max = 160) => {
  const t = (s ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  return t.length > max ? `${t.slice(0, max - 1).trimEnd()}…` : t;
};

/** Public origin from the request (works for every tenant domain), env as fallback. */
export async function siteOrigin(): Promise<string> {
  try {
    const h = await headers();
    const host = h.get('x-forwarded-host') ?? h.get('host');
    if (host && !/^(localhost|127\.|frontend|backend)/.test(host)) return `${h.get('x-forwarded-proto') ?? 'https'}://${host}`;
  } catch {
    /* outside a request */
  }
  return (process.env.NEXT_PUBLIC_SITE_URL ?? 'https://webina.dev').replace(/\/$/, '');
}

export async function siteName(): Promise<string> {
  try {
    return (await getPublicSite()).data.name || 'وبینا';
  } catch {
    return 'وبینا';
  }
}

/** Metadata with canonical, Open Graph, Twitter and robots. Editable SEO wins; fallbacks are title/excerpt/cover. */
export async function buildMetadata(opts: { path: string; seo?: Seo | null; title?: string | null; description?: string | null; image?: string | null; type?: 'website' | 'article'; publishedTime?: string | null; noindex?: boolean }): Promise<Metadata> {
  const seo = opts.seo ?? {};
  const origin = await siteOrigin();
  const title = plain(seo.title || opts.title, 120) || undefined;
  const description = plain(seo.description || opts.description) || undefined;
  const rawImage = seo.og_image || opts.image || undefined;
  const image = rawImage ? (rawImage.startsWith('http') ? rawImage : `${origin}${rawImage.startsWith('/') ? '' : '/'}${rawImage}`) : undefined;
  const canonical = seo.canonical || `${origin}${opts.path === '/' ? '' : opts.path}` || origin;
  const noindex = Boolean(seo.noindex || opts.noindex);
  const name = await siteName();
  return {
    title,
    description,
    alternates: { canonical, languages: { fa: canonical, 'x-default': canonical } },
    robots: noindex ? { index: false, follow: true } : { index: true, follow: true },
    openGraph: {
      type: opts.type ?? 'website', url: canonical, siteName: name, locale: 'fa_IR', title, description,
      images: image ? [{ url: image }] : undefined,
      ...(opts.type === 'article' && opts.publishedTime ? { publishedTime: opts.publishedTime } : {}),
    },
    twitter: { card: image ? 'summary_large_image' : 'summary', title, description, images: image ? [image] : undefined },
  };
}

/** SEO for a static route from site settings (داشبورد ← بازاریابی ← سئوی مسیرها). */
export async function routeMetadata(path: string, fallback: { title?: string; description?: string } = {}): Promise<Metadata> {
  let seo: Seo | null = null;
  try {
    const routes = (await getPublicSite()).data.branding?.seo_routes as Record<string, Seo> | undefined;
    seo = routes?.[path] ?? null;
  } catch {
    seo = null;
  }
  return buildMetadata({ path, seo, ...fallback });
}

/** Metadata for a content item fetched from the public API (deduped with the page's own fetch). */
export async function itemMetadata<T extends Record<string, unknown>>(apiPath: string, path: string, map: (d: T) => { title?: string | null; description?: string | null; image?: string | null; type?: 'website' | 'article'; publishedTime?: string | null; seo?: Seo | null }): Promise<Metadata> {
  try {
    const res = await apiServer<{ data: T }>(apiPath);
    if (!res?.data) return {};
    const m = map(res.data);
    return buildMetadata({ path, seo: m.seo ?? (res.data.seo as Seo | undefined) ?? null, ...m });
  } catch {
    return {};
  }
}

export function JsonLd({ data }: { data: Record<string, unknown> | Record<string, unknown>[] }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, '\\u003c') }} />;
}

export function breadcrumbLd(origin: string, items: { name: string; path: string }[]) {
  return { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: items.map((it, i) => ({ '@type': 'ListItem', position: i + 1, name: it.name, item: `${origin}${it.path}` })) };
}

export function articleLd(origin: string, a: { title: string; path: string; description?: string | null; image?: string | null; published?: string | null; modified?: string | null; author?: string | null; publisher: string }) {
  return {
    '@context': 'https://schema.org', '@type': 'Article', headline: a.title, description: plain(a.description) || undefined,
    image: a.image ? [a.image.startsWith('http') ? a.image : `${origin}${a.image}`] : undefined, datePublished: a.published ?? undefined, dateModified: a.modified ?? a.published ?? undefined,
    author: { '@type': a.author ? 'Person' : 'Organization', name: a.author || a.publisher }, publisher: { '@type': 'Organization', name: a.publisher }, mainEntityOfPage: `${origin}${a.path}`, inLanguage: 'fa-IR',
  };
}
