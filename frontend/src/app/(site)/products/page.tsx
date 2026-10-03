import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { PRODUCTS, copyFor } from '@/themes/webina-corporate-v1/catalog';
import { NavIcon } from '@/themes/webina-corporate-v1/components/NavIcon';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

export default async function ProductsPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  let cms = '';
  try {
    const res = await apiServer<{ data: { body_fa?: string | null; body_en?: string | null } }>('/v1/public/pages/products');
    cms = locale === 'en' ? (res.data.body_en || res.data.body_fa || '') : (res.data.body_fa || '');
  } catch {
    cms = '';
  }

  return (
    <SitePage kicker={t('site.brand')} title={t('site.nav.products')} lead={t('site.page.productsLead')}>
      <div className="card-grid cols-2">
        {PRODUCTS.map((product) => (
          <article key={product.id} id={product.id} className="panel scroll-mt-28">
            <span className="mega-ico">
              <NavIcon name={product.icon} />
            </span>
            <h2 className="mt-3 text-xl font-bold">{copyFor(locale, product.title)}</h2>
            <p className="muted mt-2 text-sm leading-7">{copyFor(locale, product.body)}</p>
            <Link href={siteHref(undefined, `portfolio/${product.portfolioSlug}`)} className="mt-3 inline-block text-sm font-bold text-[var(--saffron-deep)]">
              {t('site.nav.portfolio')}
            </Link>
          </article>
        ))}
      </div>
      {locale !== 'en' && cms ? <div className="rich-copy mt-10" dangerouslySetInnerHTML={{ __html: cms }} /> : null}
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'services')} className="btn-ghost">{t('site.nav.services')}</Link>
        <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">{t('site.nav.freeConsultation')}</Link>
      </div>
    </SitePage>
  );
}
