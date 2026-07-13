import { apiServer } from '@/src/lib/api-server';
export const revalidate = 60;
type PortfolioItem = { title: string; description?: string | null; client?: string | null };

export default async function PortfolioDetailPage({ params: { slug } }: { params: { slug: string } }) {
  let item: PortfolioItem | null = null;
  try { const res = await apiServer<{ data: PortfolioItem }>(`/v1/public/portfolio/${slug}`); item = res.data; } catch {}
  if (!item) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{item.title}</h1>
      {item.client ? <p className="text-muted-foreground mt-2">مشتری: {item.client}</p> : null}
      {item.description ? <p className="mt-6">{item.description}</p> : null}
    </div>
  );
}
