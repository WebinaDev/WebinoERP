import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { apiServer } from '@/lib/public-api-server';
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

type PageSeo = { title?: string; description?: string; og_image?: string; canonical?: string; noindex?: boolean };

/** Page metadata from the builder page's SEO fields (داشبورد ← صفحه‌ساز ← سئو); falls back to the page title. */
export async function builderPageMetadata(slug: string): Promise<Metadata> {
  try {
    const res = await apiServer<{ data?: { title?: string; seo?: PageSeo } }>(`/v1/public/builder/pages/${encodeURIComponent(slug)}`);
    const seo = res?.data?.seo ?? {};
    const title = seo.title || res?.data?.title || undefined;
    return {
      title,
      description: seo.description || undefined,
      alternates: seo.canonical ? { canonical: seo.canonical } : undefined,
      robots: seo.noindex ? { index: false, follow: true } : undefined,
      openGraph: { title, description: seo.description || undefined, images: seo.og_image ? [seo.og_image] : undefined },
    };
  } catch {
    return {};
  }
}
