import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type PortfolioSummary = { id: number; slug: string; title: string; description?: string | null; client?: string | null };

export default async function PortfolioPage({ searchParams }: { searchParams: Promise<{ service?: string; industry?: string }> }) {
  const t = await getTranslations();
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

  return (
    <SitePage kicker={t('site.nav.portfolio')} title={t('site.home.portfolio')} lead={t('site.page.portfolioLead')}>
      {items.length ? (
        <ul className="card-grid cols-3">
          {items.map((p) => (
            <li key={p.id}>
              <Link href={siteHref(undefined, `portfolio/${p.slug}`)} className="panel block">
                <div className="mb-3 h-24 rounded-xl" style={{ background: 'linear-gradient(135deg,#16130f,#8d6a24)' }} />
                <h2 className="font-bold">{p.title}</h2>
                {p.client ? <p className="mt-1 text-xs text-[var(--saffron-deep)]">{p.client}</p> : null}
                {p.description ? <p className="muted mt-2 line-clamp-3 text-sm">{p.description}</p> : null}
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyNote>{t('site.landing.portfolioEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
