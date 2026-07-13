import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';
export const revalidate = 60;

type ServiceCategory = { id: number; slug: string; name: string; children?: { slug: string; name: string }[]; services?: { slug: string; title: string }[] };

export default async function ServicesPage({ params: { locale } }: { params: { locale: string } }) {
  let categories: ServiceCategory[] = [];
  try { const res = await apiServer<{ data: ServiceCategory[] }>('/v1/public/services'); categories = res.data ?? []; } catch {}
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">خدمات</h1>
      <div className="mt-8 grid gap-8">
        {categories.map((c) => (
          <section key={c.id}>
            <h2 className="text-xl font-semibold">{c.name}</h2>
            <ul className="mt-4 grid gap-3 md:grid-cols-2">
              {c.services?.map((s) => (
                <li key={s.slug}><Link href={siteHref(undefined, `services/${s.slug}`)} className="text-[#0066FF] hover:underline">{s.title}</Link></li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}
