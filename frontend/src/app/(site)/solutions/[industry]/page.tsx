import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
export const revalidate = 60;

type SolutionIndustry = { name: string; pages?: { slug: string; title: string }[] };

export default async function SolutionIndustryPage({ params }: { params: Promise<{ industry: string }> }) {
  const t = await getTranslations();

  const { industry } = await params;

  let data: SolutionIndustry | null = null;
  try { const res = await apiServer<{ data: SolutionIndustry }>(`/v1/public/solutions/${industry}`); data = res.data; } catch {}
  if (!data) return <div className="container mx-auto px-4 py-12">{t('auto.solutions__industry__page.s_d45e5bd4')}</div>;
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">{data.name}</h1>
      <ul className="mt-8 space-y-2">
        {data.pages?.map((p) => (
          <li key={p.slug}><Link href={siteHref(undefined, `solutions/${industry}/${p.slug}`)} className="text-[#0066FF] hover:underline">{p.title}</Link></li>
        ))}
      </ul>
    </div>
  );
}
