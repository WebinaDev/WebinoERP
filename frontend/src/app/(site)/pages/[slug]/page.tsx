import { getTranslations } from 'next-intl/server';
import { resolveServerLocale } from '@/lib/server-translations';
import { apiServer } from '@/lib/public-api-server';
import { PublishedDocument } from '@/builder/public-document';
import { isDocument } from '@/builder/tree';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type CmsPageData = {
  title_fa?: string;
  title_en?: string;
  body_fa?: string | null;
  body_en?: string | null;
  document?: unknown;
};

export default async function CmsPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  const locale = await resolveServerLocale();
  let page: CmsPageData | null = null;
  try {
    const res = await apiServer<{ data: CmsPageData }>(`/v1/public/pages/${slug}`);
    page = res.data;
  } catch {
    page = null;
  }
  if (!page) return <SitePage title={t('auto._site___slug__page.s_3590a8c2')} />;
  if (isDocument(page.document)) {
    return <PublishedDocument document={page.document} />;
  }
  const title = locale === 'en' && page.title_en ? page.title_en : page.title_fa ?? '';
  const body = locale === 'en' && page.body_en ? page.body_en : page.body_fa ?? '';

  return (
    <SitePage title={title}>
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: body }} />
    </SitePage>
  );
}
