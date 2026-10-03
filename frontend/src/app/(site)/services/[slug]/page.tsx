import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { serviceBlurb } from '@/themes/webina-corporate-v1/catalog';
import { SERVICE_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type ServiceDetail = { title: string; excerpt?: string | null; body?: string | null; slug?: string };

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const locale = await getLocale();
  const { slug } = await params;

  let service: ServiceDetail | null = null;
  try {
    const res = await apiServer<{ data: ServiceDetail }>(`/v1/public/services/${slug}`);
    service = res.data;
  } catch {
    service = null;
  }

  const column = SERVICE_MEGA.columns.find(
    (col) => col.href?.endsWith(`/${slug}`) || col.items.some((item) => item.href.endsWith(`/${slug}`)),
  );
  const item = column?.items.find((entry) => entry.href.endsWith(`/${slug}`));
  const blurb = serviceBlurb(slug, locale);

  if (!service && !column && !blurb) {
    return <SitePage title={t('auto.services__slug__page.s_d45e5bd4')} />;
  }

  const title = locale === 'fa' && service?.title
    ? service.title
    : t(item?.labelKey ?? column?.titleKey ?? 'site.nav.services');

  return (
    <SitePage kicker={column ? t(column.titleKey) : t('site.nav.services')} title={title} lead={service?.excerpt || blurb || undefined}>
      {locale === 'fa' && service?.body ? (
        <div className="rich-copy" dangerouslySetInnerHTML={{ __html: service.body }} />
      ) : blurb ? (
        <p className="rich-copy">{blurb}</p>
      ) : null}
      {column ? (
        <div className="mt-10">
          <h2 className="text-lg font-bold">{t('site.page.related')}</h2>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {column.items.filter((entry) => !entry.href.endsWith(`/${slug}`)).map((entry) => (
              <li key={entry.href}>
                <Link href={siteHref(undefined, entry.href)} className="panel block">{t(entry.labelKey)}</Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">{t('site.nav.freeConsultation')}</Link>
        <Link href={siteHref(undefined, 'services')} className="btn-ghost">{t('site.home.allServices')}</Link>
      </div>
    </SitePage>
  );
}
