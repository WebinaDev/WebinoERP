import { getTranslations } from 'next-intl/server';
import { resolveServerLocale } from '@/lib/server-translations';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { HomeBlocks } from '@/themes/webina-corporate-v1/components/HomeBlocks';

export const revalidate = 60;

type HomeData = {
  data: {
    site: { name: string; branding?: Record<string, unknown> | null };
    blocks: { type: string; enabled?: boolean }[];
    announcements: { id: number; title: string; body?: string | null }[];
    testimonials: { id: number; author: string; quote: string; company?: string | null }[];
    portfolio: { id: number; slug: string; title: string; description?: string | null }[];
    blog: { id: number; slug: string; title: string; excerpt?: string | null }[];
    services?: { id: number; slug: string; name: string }[];
  };
};

export default async function SiteHomePage({ params }: { params?: Promise<Record<string, string>> }) {
  const t = await getTranslations();

  const locale = await resolveServerLocale();

  let home: HomeData['data'] | null = null;
  try {
    const res = await apiServer<HomeData>('/v1/public/home');
    home = res.data;
  } catch {
    home = null;
  }

  const name = home?.site?.name ?? t('auto.remaining.s_b5d4c8e9');

  return (
    <div>
      <HomeBlocks locale={locale} data={home} />
      <section className="container mx-auto px-4 py-16 text-center">
        <h2 className="text-2xl font-semibold">{t('auto.app__site__page.s_9d9c5687')}</h2>
        <p className="text-muted-foreground mx-auto mt-2 max-w-lg text-sm">
          {t('common.teamReadyPeriod', { name })}
        </p>
        <Button asChild className="mt-6 bg-[#0066FF] hover:bg-[#0052cc]">
          <Link href={siteHref(undefined, 'consultation')}>{t('auto.app__site__page.s_9ae4aff1')}</Link>
        </Button>
      </section>
    </div>
  );
}
