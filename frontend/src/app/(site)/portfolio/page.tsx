import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { toPersianDigits } from '@/lib/locale/calendar-date';
import { cn } from '@/lib/utils';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';
import { WorkThumb, isDarkThumb, realCover } from '@/themes/webina-corporate-v1/components/WorkThumb';

export const revalidate = 60;

type PortfolioSummary = {
  id: number;
  slug: string;
  title: string;
  description?: string | null;
  client?: string | null;
  result_metric?: string | null;
  cover_url?: string | null;
  technologies?: string[] | null;
};

export default async function PortfolioPage({ searchParams }: { searchParams: Promise<{ service?: string; industry?: string }> }) {
  const t = await getTranslations();
  const locale = await getLocale();
  const sp = await searchParams;
  const qs = new URLSearchParams();
  if (sp.service) qs.set('service', sp.service);
  if (sp.industry) qs.set('industry', sp.industry);
  let items: PortfolioSummary[] = [];
  try {
    const res = await apiServer<{ data: PortfolioSummary[] }>(`/v1/public/portfolio?${qs}`);
    items = res.data ?? [];
  } catch {
    items = [];
  }
  const num = (n: number) => {
    const s = String(n).padStart(2, '0');
    return locale === 'fa' ? toPersianDigits(s) : s;
  };

  return (
    <SitePage kicker={t('site.nav.portfolio')} title={t('site.home.portfolio')} lead={t('site.page.portfolioLead')}>
      {items.length ? (
        <div className="work-grid">
          {items.map((p, i) => {
            const cover = realCover(p.cover_url);
            return (
              <Link key={p.id} href={siteHref(undefined, `portfolio/${p.slug}`)} className="work-card">
                <div className={cn('work-thumb', !cover && isDarkThumb(i) && 'is-dark')}>
                  {cover ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={cover} alt="" className="absolute inset-0 size-full object-cover" loading="lazy" />
                  ) : (
                    <WorkThumb index={i} />
                  )}
                  <span className="work-no">{num(i + 1)}</span>
                </div>
                <div className="work-meta">
                  <h2 className="text-lg font-extrabold">{p.title}</h2>
                  {p.result_metric ? <span className="work-metric">{p.result_metric}</span> : null}
                </div>
                {p.description ? <p className="work-desc line-clamp-3">{p.description}</p> : null}
                {p.technologies?.length ? (
                  <div className="work-tags">
                    {p.technologies.slice(0, 3).map((tech) => (
                      <span key={tech}>{tech}</span>
                    ))}
                  </div>
                ) : null}
              </Link>
            );
          })}
        </div>
      ) : (
        <EmptyNote>{t('site.landing.portfolioEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
