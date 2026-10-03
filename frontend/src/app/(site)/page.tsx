import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { LandingPage, type LandingData } from '@/themes/webina-corporate-v1/components/landing/LandingPage';

export const revalidate = 60;

type HomeData = { data: NonNullable<LandingData> };

export default async function SiteHomePage() {
  const t = await getTranslations();
  let home: HomeData['data'] | null = null;
  try {
    const res = await apiServer<HomeData>('/v1/public/home');
    home = res.data;
  } catch {
    home = null;
  }

  return (
    <LandingPage
      data={
        home ?? {
          site: { name: t('site.home.defaultName') },
          testimonials: [],
          portfolio: [],
          blog: [],
          announcements: [],
          faq: [],
        }
      }
    />
  );
}
