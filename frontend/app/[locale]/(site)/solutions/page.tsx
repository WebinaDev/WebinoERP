import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function SolutionsPage({ params: { locale } }: { params: { locale: string } }) {
  let industries: { slug: string; name: string; pages?: { slug: string; title: string }[] }[] = [];
  try { const res = await apiServer<{ data: typeof industries }>('/v1/public/solutions'); industries = res.data ?? []; } catch {}
  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">راهکارها</h1>
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
