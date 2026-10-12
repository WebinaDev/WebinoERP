import { getLocale, getTranslations } from 'next-intl/server';
import { getPublicSite } from '@/lib/public-api-server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

import { resolveAbout } from '@/themes/webina-corporate-v1/about-content';
import { ABOUT_DEFAULTS } from '@/themes/webina-corporate-v1/about-defaults';
import { AboutPage } from '@/themes/webina-corporate-v1/components/about/AboutPage';
import { normalizeCompany } from '@/themes/webina-corporate-v1/site-content';

export const revalidate = 60;

export default async function AboutRoute() {
  const t = await getTranslations();
  const locale = await getLocale();
  const fa = locale === 'fa';
  // Built in the page builder («وبینا» widgets); messages are the fallback before a document exists.
  const published = fa ? await loadPublishedPage('about') : null;
  if (published) return <StorefrontDocument document={published} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(published) }} />;
  let branding: Record<string, unknown> = {};
  let siteName = t('site.brand');
  let logoUrl: string | null = null;
  try {
    const res = await getPublicSite();
    branding = (res.data.branding as Record<string, unknown> | null) ?? {};
    siteName = res.data.name ?? siteName;
    logoUrl = res.data.logo_url ?? null;
  } catch {
    /* defaults */
  }
  const defaults = ABOUT_DEFAULTS[fa ? 'fa' : 'en'];
  const sections = resolveAbout(defaults, branding.about_page, fa);

  return (
    <AboutPage
      sections={sections}
      company={fa ? normalizeCompany(branding.company) : null}
      siteName={siteName}
      logoUrl={logoUrl}
      fa={fa}
      othersLabel={t('site.aboutPageLabels.others')}
      webinaLabel={t('site.aboutPageLabels.webina')}
    />
  );
}
