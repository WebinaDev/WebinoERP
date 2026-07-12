import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function PortfolioPage({ params: { locale }, searchParams }: { params: { locale: string }; searchParams: { service?: string; industry?: string } }) {
  const qs = new URLSearchParams();
  if (searchParams.service) qs.set('service', searchParams.service);
  if (searchParams.industry) qs.set('industry', searchParams.industry);
  let items: { id: number; slug: string; title: string; description?: string | null }[] = [];
  try { const res = await apiServer<{ data: typeof items }>(`/v1/public/portfolio?${qs}`); items = res.data ?? []; } catch {}
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
