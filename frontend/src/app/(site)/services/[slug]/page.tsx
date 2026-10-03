import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { apiServer, siteHref } from '@/lib/public-api-server';

export const revalidate = 60;

type ServiceDetail = {
  title: string;
  excerpt?: string | null;
  body?: string | null;
  category?: { slug: string; name: string; services?: { slug: string; title: string }[] } | null;
};

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let service: ServiceDetail | null = null;
  try {
    const res = await apiServer<{ data: ServiceDetail }>(`/v1/public/services/${slug}`);
    service = res.data;
  } catch {
    service = null;
  }
  if (!service) return <div className="container mx-auto px-4 py-12 text-white">{t('auto.services__slug__page.s_d45e5bd4')}</div>;

  return (
    <article className="bg-[#07070a] text-white">
      <header className="border-b border-white/10 px-4 py-16 lg:px-6">
        <div className="mx-auto max-w-7xl">
          <p className="text-xs tracking-[0.2em] text-[#9cc4ff]">{service.category?.name ?? t('site.nav.services')}</p>
          <h1 className="mt-3 max-w-3xl text-4xl font-black">{service.title}</h1>
          {service.excerpt ? <p className="mt-4 max-w-2xl text-white/65">{service.excerpt}</p> : null}
        </div>
      </header>
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 lg:grid-cols-[1.4fr_0.6fr] lg:px-6">
        <div className="prose prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: service.body ?? '' }} />
        <aside className="space-y-3 text-sm">
          <Link className="block text-[#6ea8ff]" href={siteHref(undefined, 'portfolio')}>{t('site.nav.portfolio')}</Link>
          <Link className="block text-[#6ea8ff]" href={siteHref(undefined, 'solutions')}>{t('site.nav.solutions')}</Link>
          <Link className="inline-flex rounded-full bg-[#0066FF] px-4 py-2" href={siteHref(undefined, 'consultation')}>{t('site.nav.consultation')}</Link>
        </aside>
      </div>
    </article>
  );
}
