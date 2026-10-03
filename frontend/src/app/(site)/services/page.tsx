import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { SERVICE_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { NavIcon } from '@/themes/webina-corporate-v1/components/NavIcon';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type ServiceCategory = {
  slug: string;
  name: string;
  services?: { slug: string; title: string }[];
};

export default async function ServicesPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  let categories: ServiceCategory[] = [];
  try {
    const res = await apiServer<{ data: ServiceCategory[] }>('/v1/public/services');
    categories = res.data ?? [];
  } catch {
    categories = [];
  }

  const known = new Set(SERVICE_MEGA.columns.map((col) => col.href?.split('/').pop()));
  const extras = categories.filter((cat) => !known.has(cat.slug));

  return (
    <SitePage kicker={t('site.nav.services')} title={t('site.landing.servicesTitle')} lead={t('site.page.servicesLead')}>
      <div className="card-grid cols-2">
        {SERVICE_MEGA.columns.map((col) => {
          const slug = col.href?.split('/').pop() ?? '';
          const fromApi = categories.find((cat) => cat.slug === slug);
          return (
            <article key={col.titleKey} className="panel">
              <div className="flex items-center gap-2">
                <span className="mega-ico"><NavIcon name={col.icon} /></span>
                <h2 className="text-xl font-bold">
                  <Link href={siteHref(undefined, col.href || 'services')}>{locale === 'fa' && fromApi?.name ? fromApi.name : t(col.titleKey)}</Link>
                </h2>
              </div>
              <p className="muted mt-2 text-sm">{t(col.leadKey)}</p>
              <ul className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                {col.items.map((item) => {
                  const itemSlug = item.href.split('/').pop() ?? '';
                  const apiTitle = fromApi?.services?.find((s) => s.slug === itemSlug)?.title;
                  return (
                    <li key={item.href}>
                      <Link href={siteHref(undefined, item.href)} className="mega-item">
                        <span className="mega-ico"><NavIcon name={item.icon} className="size-3.5" /></span>
                        {locale === 'fa' && apiTitle ? apiTitle : t(item.labelKey)}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </article>
          );
        })}
      </div>
      {extras.length ? (
        <div className="mt-10">
          <h2 className="text-xl font-bold">{t('site.page.moreFromCms')}</h2>
          <ul className="mt-4 grid gap-3 md:grid-cols-2">
            {extras.map((cat) => (
              <li key={cat.slug} className="panel">
                <h3 className="font-bold">{cat.name}</h3>
                <ul className="mt-2 space-y-1 text-sm">
                  {cat.services?.filter((s) => s.slug !== cat.slug).map((s) => (
                    <li key={s.slug}>
                      <Link href={siteHref(undefined, `services/${s.slug}`)}>{s.title}</Link>
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </SitePage>
  );
}
