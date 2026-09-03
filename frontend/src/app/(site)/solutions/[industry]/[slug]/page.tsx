import { apiServer } from '@/lib/public-api-server';
export const revalidate = 60;

type SolutionPageData = { title: string; body?: string | null };

export default async function SolutionPage({ params }: { params: Promise<{ industry: string; slug: string }> }) {
  const { industry, slug } = await params;

  let page: SolutionPageData | null = null;
  try { const res = await apiServer<{ data: SolutionPageData }>(`/v1/public/solutions/${industry}/${slug}`); page = res.data; } catch {}
  if (!page) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{page.title}</h1>
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: page.body ?? '' }} />
    </article>
  );
}
