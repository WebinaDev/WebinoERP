import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function Page({ params: { locale } }: { params: { locale: string } }) {
  let items: { id: number; slug: string; title: string; excerpt?: string | null; description?: string | null }[] = [];
  try { const res = await apiServer<{ data: typeof items }>('/v1/public/magazine'); items = res.data ?? []; } catch {}
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">مجله</h1>
      <ul className="mt-8 grid gap-6 md:grid-cols-2">
        {items.map((p) => (
          <li key={p.id} className="rounded-xl border p-5"><Link href={siteHref(undefined, `magazine/${p.slug}`)}><h2 className="font-semibold">{p.title}</h2></Link></li>
        ))}
      </ul>
    </div>
  );
}
