import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
export const revalidate = 60;

type SolutionIndustrySummary = { slug: string; name: string; pages?: { slug: string; title: string }[] };

export default async function SolutionsPage({ params }: { params?: Promise<Record<string, string>> }) {
  const t = await getTranslations();

  let industries: SolutionIndustrySummary[] = [];
  try { const res = await apiServer<{ data: SolutionIndustrySummary[] }>('/v1/public/solutions'); industries = res.data ?? []; } catch {}
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">{t('auto._site__solutions_page.s_193bb253')}</h1>
      <div className="mt-8 grid gap-8 md:grid-cols-2">
        {industries.map((ind) => (
          <section key={ind.slug} className="rounded-xl border p-6">
            <h2 className="text-xl font-semibold"><Link href={siteHref(undefined, `solutions/${ind.slug}`)}>{ind.name}</Link></h2>
          </section>
        ))}
      </div>
    </div>
  );
}
