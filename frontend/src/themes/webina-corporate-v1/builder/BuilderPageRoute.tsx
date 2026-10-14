import { notFound } from 'next/navigation';
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
