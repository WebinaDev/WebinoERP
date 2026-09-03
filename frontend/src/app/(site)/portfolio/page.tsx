import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
export const revalidate = 60;

type PortfolioSummary = { id: number; slug: string; title: string; description?: string | null };

export default async function PortfolioPage({ params, searchParams }: { params?: Promise<Record<string, string>>; searchParams: Promise<{ service?: string; industry?: string }> }) {
  const sp = await searchParams;

  const qs = new URLSearchParams();
  if (sp.service) qs.set('service', sp.service);
  if (sp.industry) qs.set('industry', sp.industry);
  let items: PortfolioSummary[] = [];
  try { const res = await apiServer<{ data: PortfolioSummary[] }>(`/v1/public/portfolio?${qs}`); items = res.data ?? []; } catch {}
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">نمونه‌کارها</h1>
      <ul className="mt-8 grid gap-6 md:grid-cols-3">
        {items.map((p) => (
          <li key={p.id} className="rounded-xl border p-5"><Link href={siteHref(undefined, `portfolio/${p.slug}`)}><h2 className="font-semibold">{p.title}</h2></Link></li>
        ))}
      </ul>
    </div>
  );
}
