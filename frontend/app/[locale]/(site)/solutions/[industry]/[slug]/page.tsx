import { apiServer } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function SolutionPage({ params: { industry, slug } }: { params: { industry: string; slug: string } }) {
  let page: { title: string; body?: string | null } | null = null;
  try { const res = await apiServer<{ data: typeof page }>(`/v1/public/solutions/${industry}/${slug}`); page = res.data; } catch {}
  if (!page) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{page.title}</h1>
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: page.body ?? '' }} />
    </article>
  );
}
