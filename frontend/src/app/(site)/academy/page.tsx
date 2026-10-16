import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { itemMetadata, type Seo } from '@/lib/seo';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

/** Builder-only academy hub: صفحه‌ساز page «academy». Course cards come from داشبورد ← بازاریابی ← آکادمی. */
export async function generateMetadata(): Promise<Metadata> {
  return itemMetadata<{ title?: string; seo?: Seo }>('/v1/public/builder/pages/academy', '/academy', (d) => ({ title: d.title || 'آکادمی وبینا', seo: d.seo }));
}

export default async function AcademyPage() {
  const doc = await loadPublishedPage('academy');
  if (!doc) notFound();
  return <StorefrontDocument document={doc} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(doc) }} />;
}
