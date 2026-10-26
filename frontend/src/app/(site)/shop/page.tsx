import { notFound } from 'next/navigation';
import { itemMetadata, type Seo } from '@/lib/seo';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

// Content is owned by the published page builder page «shop».
export async function generateMetadata() {
  return itemMetadata<{ title?: string; seo?: Seo }>('/v1/public/builder/pages/shop', '/shop', (d) => ({ title: d.title || 'فروشگاه', seo: d.seo }));
}

export default async function Page() {
  const built = await loadPublishedPage('shop');
  if (!built) notFound();
  return <StorefrontDocument document={built} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(built) }} />;
}
