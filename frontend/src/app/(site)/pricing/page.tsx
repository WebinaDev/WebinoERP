import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { siteHref } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export default async function PricingPage() {
  const t = await getTranslations();
  return (
    <SitePage kicker={t('site.nav.pricing')} title={t('site.nav.pricing')} lead={t('site.page.pricingLead')}>
      <p className="rich-copy max-w-2xl">{t('site.page.pricingBody')}</p>
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">{t('site.nav.freeConsultation')}</Link>
        <Link href={siteHref(undefined, 'proposal')} className="btn-ghost">{t('site.nav.proposal')}</Link>
      </div>
    </SitePage>
  );
}
