import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { SOLUTION_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { NavIcon } from '@/themes/webina-corporate-v1/components/NavIcon';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type Industry = { slug: string; name: string; pages?: { slug: string; title: string }[] };

export default async function SolutionsPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  let industries: Industry[] = [];
  try {
    const res = await apiServer<{ data: Industry[] }>('/v1/public/solutions');
    industries = res.data ?? [];
  } catch {
    industries = [];
  }
  const known = new Set(SOLUTION_MEGA.columns.map((col) => col.href?.split('/').pop()));

  return (
    <SitePage kicker={t('site.nav.solutions')} title={t('site.landing.solutionsTitle')} lead={t('site.page.solutionsLead')}>
      <div className="card-grid cols-2">
        {SOLUTION_MEGA.columns.map((col) => {
          const slug = col.href?.split('/').pop() ?? '';
          const fromApi = industries.find((row) => row.slug === slug);
          return (
            <article key={col.titleKey} className="panel">
              <div className="flex items-center gap-2">
                <span className="mega-ico"><NavIcon name={col.icon} /></span>
                <h2 className="text-xl font-bold">
                  <Link href={siteHref(undefined, col.href || 'solutions')}>{locale === 'fa' && fromApi?.name ? fromApi.name : t(col.titleKey)}</Link>
                </h2>
              </div>
              <p className="muted mt-2 text-sm">{t(col.leadKey)}</p>
              <ul className="mt-4 space-y-2 text-sm">
                {col.items.map((item) => {
                  const itemSlug = item.href.split('/').pop() ?? '';
                  const apiTitle = fromApi?.pages?.find((p) => p.slug === itemSlug)?.title;
                  return (
                    <li key={item.href}>
                      <Link href={siteHref(undefined, item.href)}>{locale === 'fa' && apiTitle ? apiTitle : t(item.labelKey)}</Link>
                    </li>
                  );
                })}
              </ul>
            </article>
          );
        })}
      </div>
      {industries.filter((row) => !known.has(row.slug)).length ? (
        <div className="mt-10">
          <h2 className="text-xl font-bold">{t('site.page.moreFromCms')}</h2>
          <ul className="card-grid cols-3">
            {industries.filter((row) => !known.has(row.slug)).map((row) => (
              <li key={row.slug} className="panel">
                <Link href={siteHref(undefined, `solutions/${row.slug}`)} className="font-bold">{row.name}</Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </SitePage>
  );
}
