import { apiServer } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function MagazinePostPage({ params: { slug } }: { params: { slug: string } }) {
  let post: { title: string; body?: string | null } | null = null;
  try { const res = await apiServer<{ data: typeof post }>(`/v1/public/magazine/${slug}`); post = res.data; } catch {}
  if (!post) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{post.title}</h1>
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
    </article>
  );
}
