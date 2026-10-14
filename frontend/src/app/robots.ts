import type { MetadataRoute } from 'next';
import { siteOrigin } from '@/lib/seo';

/** Per-page noindex is sent as <meta name="robots"> and those pages are left out of the sitemap. */
export default async function robots(): Promise<MetadataRoute.Robots> {
  const base = await siteOrigin();
  return {
    rules: { userAgent: '*', allow: '/', disallow: ['/admin/', '/dashboard/', '/login', '/api/', '/rahn/'] },
    sitemap: `${base}/sitemap.xml`,
    host: base,
  };
}
