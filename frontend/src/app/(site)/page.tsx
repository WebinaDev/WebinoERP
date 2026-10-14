import { routeMetadata } from '@/lib/seo';
import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

import { LandingPage, type LandingData } from '@/themes/webina-corporate-v1/components/landing/LandingPage';

export const revalidate = 60;

type HomeData = { data: NonNullable<LandingData> };

export function generateMetadata() {
  return routeMetadata('/', {});
}

export default async function SiteHomePage() {
  const t = await getTranslations();
  let home: HomeData['data'] | null = null;
  try {
    const res = await apiServer<HomeData>('/v1/public/home');
    home = res.data;
  } catch {
    home = null;
  }

  const publishedHome = await loadPublishedPage('home');
  if (publishedHome) {
    return <StorefrontDocument document={publishedHome} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(publishedHome) }} />;
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
