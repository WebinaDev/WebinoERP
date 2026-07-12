import { MetadataRoute } from 'next';

export default function sitemap(): MetadataRoute.Sitemap {
  const base = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://webina.ir';
  const paths = [
    '',
    'blog',
    'academy',
    'magazine',
    'portfolio',
    'services',
    'solutions',
    'about',
    'contact',
    'consultation',
    'faq',
    'team',
  ];

  return paths.map((path) => ({
    url: `${base}${path ? `/${path}` : ''}`,
    changeFrequency: 'daily' as const,
    priority: path === '' ? 1 : 0.7,
  }));
}
