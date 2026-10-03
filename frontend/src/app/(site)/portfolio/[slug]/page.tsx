import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { apiServer, siteHref } from '@/lib/public-api-server';

export const revalidate = 60;

type PortfolioItem = {
  title: string;
  description?: string | null;
  client?: string | null;
  result_metric?: string | null;
  case_study?: string | null;
  cover_url?: string | null;
  technologies?: string[] | null;
  category?: { name: string } | null;
  service?: { slug: string; title: string } | null;
  industry?: { slug: string; name: string } | null;
};

export default async function PortfolioDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let item: PortfolioItem | null = null;
  try {
    const res = await apiServer<{ data: PortfolioItem }>(`/v1/public/portfolio/${slug}`);
    item = res.data;
  } catch {
    item = null;
  }
  if (!item) return <div className="container mx-auto px-4 py-12 text-white">{t('auto.portfolio__slug__page.s_d45e5bd4')}</div>;

  return (
    <article className="bg-[#07070a] text-white">
      <header className="relative overflow-hidden border-b border-white/10">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_0%,#0066FF44,transparent_40%)]" />
        <div className="relative mx-auto grid max-w-7xl gap-8 px-4 py-16 lg:grid-cols-2 lg:px-6">
          <div>
            <p className="text-xs tracking-[0.2em] text-[#9cc4ff]">{item.category?.name ?? t('site.nav.portfolio')}</p>
            <h1 className="mt-3 text-4xl font-black sm:text-5xl">{item.title}</h1>
            {item.result_metric ? <p className="mt-4 text-2xl font-semibold text-[#6ea8ff]">{item.result_metric}</p> : null}
            {item.client ? <p className="mt-3 text-white/60">{item.client}</p> : null}
          </div>
          {item.cover_url ? <img src={item.cover_url} alt="" className="h-64 w-full rounded-3xl object-cover" /> : null}
        </div>
      </header>
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 lg:grid-cols-[1.4fr_0.6fr] lg:px-6">
        <div className="prose prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: item.case_study || `<p>${item.description ?? ''}</p>` }} />
        <aside className="space-y-4 text-sm text-white/70">
          {item.technologies?.length ? <p>{item.technologies.join(' · ')}</p> : null}
          {item.service ? <Link className="block text-[#6ea8ff]" href={siteHref(undefined, `services/${item.service.slug}`)}>{item.service.title}</Link> : null}
          {item.industry ? <Link className="block text-[#6ea8ff]" href={siteHref(undefined, `solutions/${item.industry.slug}`)}>{item.industry.name}</Link> : null}
          <Link className="inline-flex rounded-full bg-[#0066FF] px-4 py-2 text-white" href={siteHref(undefined, 'consultation')}>{t('site.nav.freeConsultation')}</Link>
        </aside>
      </div>
    </article>
  );
}
