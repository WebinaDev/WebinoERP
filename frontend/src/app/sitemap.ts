import type { MetadataRoute } from 'next';
import { apiServer } from '@/lib/public-api-server';
import { siteOrigin } from '@/lib/seo';

export const revalidate = 3600;

/** Every published, indexable URL from the CMS (builder pages + all content types); noindex items are excluded server-side. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = await siteOrigin();
  let rows: { path: string; lastmod?: string | null }[] = [];
  try {
    rows = (await apiServer<{ data: { path: string; lastmod?: string | null }[] }>('/v1/public/sitemap', { revalidate: 3600 })).data ?? [];
  } catch {
    rows = [{ path: '/' }];
  }
  return rows.map((r) => ({
    url: `${base}${r.path === '/' ? '' : r.path}`,
    ...(r.lastmod ? { lastModified: r.lastmod } : {}),
    changeFrequency: r.path.split('/').length > 2 ? ('weekly' as const) : ('daily' as const),
    priority: r.path === '/' ? 1 : r.path.split('/').length > 2 ? 0.6 : 0.8,
  }));
}
