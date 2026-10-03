import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { apiServer, siteHref } from '@/lib/public-api-server';

export const revalidate = 60;

type Item = {
  id: number;
  slug: string;
  title: string;
  description?: string | null;
  client?: string | null;
  result_metric?: string | null;
  cover_url?: string | null;
  category?: { slug: string; name: string } | null;
};

export default async function PortfolioPage({
  searchParams,
}: {
  searchParams: Promise<{ category?: string }>;
}) {
  const t = await getTranslations();
  const sp = await searchParams;
  const qs = new URLSearchParams();
  if (sp.category) qs.set('category', sp.category);
  let items: Item[] = [];
  try {
    const res = await apiServer<{ data: Item[] }>(`/v1/public/portfolio?${qs}`);
    items = res.data ?? [];
  } catch {
    items = [];
  }
  const categories = items.reduce<{ slug: string; name: string }[]>((acc, item) => {
    const cat = item.category;
    if (cat && !acc.some((row) => row.slug === cat.slug)) acc.push(cat);
    return acc;
  }, []);

  return (
    <div className="bg-[#07070a] text-white">
      <header className="relative overflow-hidden border-b border-white/10">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_0%,#0066FF33,transparent_45%)]" />
        <div className="relative mx-auto max-w-7xl px-4 py-16 lg:px-6">
          <p className="text-xs tracking-[0.22em] text-[#9cc4ff]">{t('site.nav.portfolio')}</p>
          <h1 className="mt-3 max-w-3xl text-4xl font-black sm:text-5xl">{t('site.home.portfolio')}</h1>
          <p className="mt-4 max-w-2xl text-white/60">{t('site.landing.workLead')}</p>
          <div className="mt-8 flex flex-wrap gap-2">
            <Link href={siteHref(undefined, 'portfolio')} className="rounded-full border border-white/15 px-3 py-1 text-sm">{t('common.all')}</Link>
            {categories.map((cat) => cat ? (
              <Link key={cat.slug} href={siteHref(undefined, `portfolio?category=${cat.slug}`)} className="rounded-full border border-white/15 px-3 py-1 text-sm hover:border-[#0066FF]">
                {cat.name}
              </Link>
            ) : null)}
          </div>
        </div>
      </header>
      <ul className="mx-auto grid max-w-7xl gap-5 px-4 py-12 md:grid-cols-2 lg:grid-cols-3 lg:px-6">
        {items.map((item) => (
          <li key={item.id}>
            <Link href={siteHref(undefined, `portfolio/${item.slug}`)} className="group block overflow-hidden rounded-3xl border border-white/10 bg-white/[0.03]">
              <div className="relative h-44 overflow-hidden bg-gradient-to-br from-[#0066FF]/50 to-[#07070a]">
                {item.cover_url ? <img src={item.cover_url} alt="" className="h-full w-full object-cover transition duration-700 group-hover:scale-105" /> : null}
              </div>
              <div className="space-y-2 p-5">
                <p className="text-xs text-[#9cc4ff]">{item.category?.name ?? item.client}</p>
                <h2 className="text-xl font-semibold">{item.title}</h2>
                {item.result_metric ? <p className="text-sm font-medium text-[#6ea8ff]">{item.result_metric}</p> : null}
                <p className="line-clamp-2 text-sm text-white/55">{item.description}</p>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
