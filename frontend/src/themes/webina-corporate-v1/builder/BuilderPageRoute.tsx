import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { itemMetadata, type Seo } from '@/lib/seo';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

/**
 * A site route whose content is only the published page-builder document with this slug
 * (داشبورد ← بازاریابی ← برگه‌ها / صفحه‌ساز). No content lives in code; without a published page it 404s.
 */
export async function BuilderPageRoute({ slug }: { slug: string }) {
  const published = await loadPublishedPage(slug);
  if (!published) notFound();
  return <StorefrontDocument document={published} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(published) }} />;
}

/** Page metadata from the builder page's SEO fields (صفحه‌ساز ← سئو); falls back to the page title. */
export async function builderPageMetadata(slug: string): Promise<Metadata> {
  return itemMetadata<{ title?: string; seo?: Seo }>(`/v1/public/builder/pages/${encodeURIComponent(slug)}`, slug === 'home' ? '/' : `/${slug}`, (d) => ({ title: d.title, seo: d.seo }));
}
