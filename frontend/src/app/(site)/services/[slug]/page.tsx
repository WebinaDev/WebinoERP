import type { Metadata } from 'next';
import { getLocale, getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { itemMetadata, type Seo } from '@/lib/seo';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { serviceBlurb } from '@/themes/webina-corporate-v1/catalog';
import { SERVICE_MEGA } from '@/themes/webina-corporate-v1/site-nav';
import { findHomeService } from '@/themes/webina-corporate-v1/home-content';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';
import { getHomeBlock } from '@/themes/webina-corporate-v1/site-content-server';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

type ServiceDetail = { title: string; excerpt?: string | null; body?: string | null; slug?: string };

/** Builder page slug equals the service slug (e.g. custom-web). Content lives only in صفحه‌ساز. */
function builderSlug(slug: string) {
  return slug;
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const fromBuilder = await itemMetadata<{ title?: string; seo?: Seo }>(
    `/v1/public/builder/pages/${encodeURIComponent(builderSlug(slug))}`,
    `/services/${slug}`,
    (d) => ({ title: d.title, seo: d.seo }),
  );
  // itemMetadata returns a soft title even on miss; prefer builder when seo/title present via second fetch fallback below.
  try {
    const res = await apiServer<{ data?: { document?: unknown; title?: string; seo?: Seo } }>(
      `/v1/public/builder/pages/${encodeURIComponent(builderSlug(slug))}`,
      { revalidate: 0 },
    );
    if (res?.data?.document) return fromBuilder;
  } catch {
    /* fall through */
  }
  return itemMetadata<Record<string, unknown>>(
    `/v1/public/services/${encodeURIComponent(slug)}`,
    `/services/${slug}`,
    (d) => ({
      title: (d.title || d.name) as string,
      description: (d.excerpt || d.description || d.body) as string,
      image: (d.cover_url || d.image_url) as string,
    }),
  );
}

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const locale = await getLocale();
  const { slug } = await params;

  const published = locale === 'fa' ? await loadPublishedPage(builderSlug(slug)) : null;
  if (published) {
    return (
      <StorefrontDocument
        document={published}
        themeClass="webina-builder"
        runtime={{ prefetch: await prefetchCorporate(published) }}
      />
    );
  }

  let service: ServiceDetail | null = null;
  try {
    const res = await apiServer<{ data: ServiceDetail }>(`/v1/public/services/${slug}`);
    service = res.data;
  } catch {
    service = null;
  }

  const homeService = findHomeService(slug);
  const cmsService = (await getHomeBlock('services')).items.find((entry) => entry.href.replace(/^\//, '') === `services/${slug}`);
  const column = SERVICE_MEGA.columns.find(
    (col) =>
      col.href?.endsWith(`/${slug}`) ||
      col.items.some((item) => item.href.endsWith(`/${slug}`)) ||
      (homeService ? col.href === homeService.related : false),
  );
  const item = column?.items.find((entry) => entry.href.endsWith(`/${slug}`));
  const blurb = cmsService?.body || (homeService ? t(`site.v2.service.${homeService.key}Body`) : serviceBlurb(slug, locale));

  if (!service && !column && !blurb && !cmsService) {
    return <SitePage title={t('auto.services__slug__page.s_d45e5bd4')} />;
  }

  const title =
    locale === 'fa' && service?.title
      ? service.title
      : cmsService?.title
        ? cmsService.title
        : homeService
          ? t(`site.v2.service.${homeService.key}Title`)
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
            {column.items
              .filter((entry) => !entry.href.endsWith(`/${slug}`))
              .map((entry) => (
                <li key={entry.href}>
                  <Link href={siteHref(undefined, entry.href)} className="panel block">
                    {t(entry.labelKey)}
                  </Link>
                </li>
              ))}
          </ul>
        </div>
      ) : null}
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">
          {t('site.nav.freeConsultation')}
        </Link>
        <Link href={siteHref(undefined, 'services')} className="btn-ghost">
          {t('site.home.allServices')}
        </Link>
      </div>
    </SitePage>
  );
}
