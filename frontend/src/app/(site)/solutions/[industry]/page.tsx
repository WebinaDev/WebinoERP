import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { solutionBlurb } from '@/themes/webina-corporate-v1/catalog';
import { SOLUTION_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type SolutionIndustry = { name: string; description?: string | null; pages?: { slug: string; title: string }[] };

export default async function SolutionIndustryPage({ params }: { params: Promise<{ industry: string }> }) {
  const t = await getTranslations();
  const locale = await getLocale();
  const { industry } = await params;
  let data: SolutionIndustry | null = null;
  try {
    const res = await apiServer<{ data: SolutionIndustry }>(`/v1/public/solutions/${industry}`);
    data = res.data;
  } catch {
    data = null;
  }
  const column = SOLUTION_MEGA.columns.find((col) => col.href?.endsWith(`/${industry}`));
  if (!data && !column) {
    return <SitePage title={t('auto.solutions__industry__page.s_d45e5bd4')} />;
  }
  const title = locale === 'fa' && data?.name ? data.name : t(column?.titleKey ?? 'site.nav.solutions');
  const pages = column?.items ?? [];

  return (
    <SitePage kicker={t('site.nav.solutions')} title={title} lead={data?.description || solutionBlurb(industry, locale) || (column ? t(column.leadKey) : undefined)}>
      <ul className="card-grid cols-2">
        {(data?.pages?.length ? data.pages.map((p) => ({ slug: p.slug, title: p.title, href: `solutions/${industry}/${p.slug}` })) : pages.map((item) => ({
          slug: item.href,
          title: t(item.labelKey),
          href: item.href,
        }))).map((page) => (
          <li key={page.slug}>
            <Link href={siteHref(undefined, page.href)} className="panel block font-semibold">{page.title}</Link>
          </li>
        ))}
      </ul>
    </SitePage>
  );
}
