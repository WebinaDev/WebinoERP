import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
export const revalidate = 60;

type MagazinePost = { title: string; body?: string | null };

export default async function MagazinePostPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();

  const { slug } = await params;

  let post: MagazinePost | null = null;
  try { const res = await apiServer<{ data: MagazinePost }>(`/v1/public/magazine/${slug}`); post = res.data; } catch {}
  if (!post) return <div className="container mx-auto px-4 py-12">{t('auto.magazine__slug__page.s_d45e5bd4')}</div>;
  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{post.title}</h1>
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
    </article>
  );
}
