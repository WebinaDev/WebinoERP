import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function SolutionIndustryPage({ params: { locale, industry } }: { params: { locale: string; industry: string } }) {
  let data: { name: string; pages?: { slug: string; title: string }[] } | null = null;
  try { const res = await apiServer<{ data: typeof data }>(`/v1/public/solutions/${industry}`); data = res.data; } catch {}
  if (!data) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
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
