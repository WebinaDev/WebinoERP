import { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  const base = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://webina.ir';
  return {
    rules: { userAgent: '*', allow: '/', disallow: ['/dashboard/', '/login'] },
    sitemap: `${base}/sitemap.xml`,
  };
}
