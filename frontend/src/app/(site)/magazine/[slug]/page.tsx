import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

export default async function MagazinePostPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let post: { title: string; body?: string | null; excerpt?: string | null } | null = null;
  try {
    const res = await apiServer<{ data: { title: string; body?: string | null; excerpt?: string | null } }>(`/v1/public/magazine/${slug}`);
    post = res.data;
  } catch {
    post = null;
  }
  if (!post) return <SitePage title={t('auto.magazine__slug__page.s_d45e5bd4')} />;

  return (
    <SitePage kicker={t('site.nav.magazine')} title={post.title} lead={post.excerpt || undefined}>
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
    </SitePage>
  );
}
