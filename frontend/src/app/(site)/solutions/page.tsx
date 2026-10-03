import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { apiServer, siteHref } from '@/lib/public-api-server';

export const revalidate = 60;

type SolutionIndustrySummary = {
  slug: string;
  name: string;
  description?: string | null;
  pages?: { slug: string; title: string }[];
};

export default async function SolutionsPage() {
  const t = await getTranslations();
  let industries: SolutionIndustrySummary[] = [];
  try {
    const res = await apiServer<{ data: SolutionIndustrySummary[] }>('/v1/public/solutions');
    industries = res.data ?? [];
  } catch {
    industries = [];
  }

  return (
    <div className="bg-[#07070a] text-white">
      <header className="border-b border-white/10 px-4 py-16 lg:px-6">
        <div className="mx-auto max-w-7xl">
          <p className="text-xs tracking-[0.22em] text-[#9cc4ff]">{t('site.nav.solutions')}</p>
          <h1 className="mt-3 text-4xl font-black">{t('site.landing.solutionsTitle')}</h1>
          <p className="mt-4 max-w-2xl text-white/60">{t('site.landing.solutionsLead')}</p>
        </div>
      </header>
      <div className="mx-auto grid max-w-7xl gap-6 px-4 py-12 md:grid-cols-2 lg:px-6">
        {industries.map((industry) => (
          <section key={industry.slug} className="rounded-3xl border border-white/10 p-6">
            <h2 className="text-2xl font-semibold">
              <Link href={siteHref(undefined, `solutions/${industry.slug}`)}>{industry.name}</Link>
            </h2>
            {industry.description ? <p className="mt-2 text-sm text-white/55">{industry.description}</p> : null}
            <ul className="mt-4 space-y-2 text-sm text-[#9cc4ff]">
              {industry.pages?.map((page) => (
                <li key={page.slug}>
                  <Link href={siteHref(undefined, `solutions/${industry.slug}/${page.slug}`)}>{page.title}</Link>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}
