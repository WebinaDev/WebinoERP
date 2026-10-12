'use client';

import { useLocale, useTranslations } from 'next-intl';
import { resolveAbout } from '../about-content';
import { ABOUT_DEFAULTS } from '../about-defaults';
import { AboutPage } from '../components/about/AboutPage';
import { ContactSection } from '../components/contact/ContactSection';
import { LandingPage, type LandingData } from '../components/landing/LandingPage';
import { normalizeCompany } from '../site-content';
import { usePublicData } from './usePublicData';
import '../components/public-site.css';

type Site = { name?: string; logo_url?: string | null; branding?: Record<string, unknown> | null };

function HomeWidget({ type, props }: { type: string; props: Record<string, unknown> }) {
  const home = usePublicData<NonNullable<LandingData>>('/v1/public/home');
  return <LandingPage data={{ ...(home ?? {}), blocks: [{ type, enabled: true, props }] }} />;
}

function AboutWidget({ type, props }: { type: string; props: Record<string, unknown> }) {
  const t = useTranslations();
  const fa = useLocale() === 'fa';
  const site = usePublicData<Site>('/v1/public/site');
  const defaults = ABOUT_DEFAULTS[fa ? 'fa' : 'en'];
  const sections = resolveAbout(defaults, { sections: [{ type, enabled: true, props }] }, fa);
  return (
    <AboutPage
      sections={sections}
      company={fa && type === 'intro' ? normalizeCompany(site?.branding?.company) : null}
      siteName={site?.name ?? t('site.brand')}
      logoUrl={site?.logo_url ?? null}
      fa={fa}
      othersLabel={t('site.aboutPageLabels.others')}
      webinaLabel={t('site.aboutPageLabels.webina')}
    />
  );
}

/** Renderer for every «وبینا» builder widget (type `webina-<page>-<section>`). */
export function CorporateWidget({ type, props }: { type: string; props: Record<string, unknown> }) {
  return (
    <div className="webina-public webina-widget">
      <Section type={type} props={props} />
    </div>
  );
}

function Section({ type, props }: { type: string; props: Record<string, unknown> }) {
  const rest = type.replace(/^webina-/, '');
  if (rest === 'cta-band') return <ContactSection id="cta" props={props} />;
  const [page, ...parts] = rest.split('-');
  const section = parts.join('-');
  if (page === 'home') return <HomeWidget type={section} props={props} />;
  if (page === 'contact') return <ContactSection id={section} props={props} />;
  if (page === 'about') return <AboutWidget type={section} props={props} />;
  return null;
}
