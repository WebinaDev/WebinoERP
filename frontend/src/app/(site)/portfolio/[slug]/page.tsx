import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type PortfolioItem = { title: string; description?: string | null; client?: string | null };

export default async function PortfolioDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let item: PortfolioItem | null = null;
  try {
    const res = await apiServer<{ data: PortfolioItem }>(`/v1/public/portfolio/${slug}`);
    item = res.data;
  } catch {
    item = null;
  }
  if (!item) return <SitePage title={t('auto.portfolio__slug__page.s_d45e5bd4')} />;

  return (
    <SitePage kicker={item.client || t('site.nav.portfolio')} title={item.title} lead={item.description || undefined}>
      <Link href={siteHref(undefined, 'products')} className="text-sm font-bold text-[var(--saffron-deep)]">{t('site.nav.products')}</Link>
    </SitePage>
  );
}
