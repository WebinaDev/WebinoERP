import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

export default async function AboutPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  let title = t('site.nav.about');
  let body = '';
  try {
    const res = await apiServer<{ data: { title_fa?: string; title_en?: string; body_fa?: string | null; body_en?: string | null } }>('/v1/public/pages/about');
    title = (locale === 'en' ? res.data.title_en : res.data.title_fa) || res.data.title_fa || title;
    const fa = res.data.body_fa || '';
    const en = res.data.body_en || '';
    body = locale === 'en' ? (en && en !== fa ? en : '') : fa;
  } catch {
    body = '';
  }

  return (
    <SitePage kicker={t('site.footer.legalName')} title={title} lead={t('site.page.aboutLead')}>
      {body ? <div className="rich-copy" dangerouslySetInnerHTML={{ __html: body }} /> : <p className="rich-copy">{t('site.page.aboutFallback')}</p>}
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'products')} className="btn-saffron">{t('site.nav.products')}</Link>
        <Link href={siteHref(undefined, 'services')} className="btn-ghost">{t('site.nav.services')}</Link>
        <Link href={siteHref(undefined, 'contact')} className="btn-ghost">{t('site.nav.contact')}</Link>
      </div>
    </SitePage>
  );
}
