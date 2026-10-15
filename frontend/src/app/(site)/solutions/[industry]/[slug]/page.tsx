import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { itemMetadata, type Seo } from '@/lib/seo';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { solutionBlurb } from '@/themes/webina-corporate-v1/catalog';
import { SOLUTION_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

type SolutionPageData = { title: string; body?: string | null };

/** Builder slug: `{industry}-{slug}` e.g. retail-fashion */
function builderSlug(industry: string, slug: string) {
  return `${industry}-${slug}`;
}

export async function generateMetadata({ params }: { params: Promise<{ industry: string; slug: string }> }): Promise<Metadata> {
  const { industry, slug } = await params;
  try {
    const res = await apiServer<{ data?: { document?: unknown; title?: string; seo?: Seo } }>(
      `/v1/public/builder/pages/${encodeURIComponent(builderSlug(industry, slug))}`,
      { revalidate: 0 },
    );
    if (res?.data?.document) {
      return itemMetadata<{ title?: string; seo?: Seo }>(
        `/v1/public/builder/pages/${encodeURIComponent(builderSlug(industry, slug))}`,
        `/solutions/${industry}/${slug}`,
        (d) => ({ title: d.title, seo: d.seo }),
      );
    }
  } catch {
    /* fall through */
  }
  return itemMetadata<Record<string, unknown>>(
    `/v1/public/solutions/${encodeURIComponent(industry)}/${encodeURIComponent(slug)}`,
    `/solutions/${industry}/${slug}`,
    (d) => ({ title: d.title as string, description: d.body as string }),
  );
}

export default async function SolutionPage({ params }: { params: Promise<{ industry: string; slug: string }> }) {
  const t = await getTranslations();
  const locale = await getLocale();
  const { industry, slug } = await params;

  const published = locale === 'fa' ? await loadPublishedPage(builderSlug(industry, slug)) : null;
  if (published) {
    return (
      <StorefrontDocument
        document={published}
        themeClass="webina-builder"
        runtime={{ prefetch: await prefetchCorporate(published) }}
      />
    );
  }

  let page: SolutionPageData | null = null;
  try {
    const res = await apiServer<{ data: SolutionPageData }>(`/v1/public/solutions/${industry}/${slug}`);
    page = res.data;
  } catch {
    page = null;
  }
  const column = SOLUTION_MEGA.columns.find((col) => col.href?.endsWith(`/${industry}`));
  const item = column?.items.find((entry) => entry.href.endsWith(`/${slug}`));
  if (!page && !item) {
    return <SitePage title={t('auto._industry___slug__page.s_d45e5bd4')} />;
  }
  const title = locale === 'fa' && page?.title ? page.title : t(item?.labelKey ?? 'site.nav.solutions');
  const blurb = solutionBlurb(slug, locale);

  return (
    <SitePage kicker={column ? t(column.titleKey) : t('site.nav.solutions')} title={title} lead={blurb || undefined}>
      {locale === 'fa' && page?.body ? (
        <div className="rich-copy" dangerouslySetInnerHTML={{ __html: page.body }} />
      ) : null}
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">
          {t('site.nav.freeConsultation')}
        </Link>
        <Link href={siteHref(undefined, `solutions/${industry}`)} className="btn-ghost">
          {t('site.nav.solutions')}
        </Link>
      </div>
    </SitePage>
  );
}
