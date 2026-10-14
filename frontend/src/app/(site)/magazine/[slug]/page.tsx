import { articleLd, breadcrumbLd, itemMetadata, JsonLd, siteName, siteOrigin } from '@/lib/seo';
import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/magazine/${encodeURIComponent(slug)}`, `/magazine/${slug}`, (d) => ({ title: d.title as string, description: (d.excerpt || d.body) as string, image: d.cover_url as string, type: 'article' as const, publishedTime: d.published_at as string }));
}

type Mag = { title: string; body?: string | null; excerpt?: string | null; cover_url?: string | null; author?: string | null; published_at?: string | null; updated_at?: string | null };

export default async function MagazinePostPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let post: Mag | null = null;
  try {
    const res = await apiServer<{ data: Mag }>(`/v1/public/magazine/${slug}`);
    post = res.data;
  } catch {
    post = null;
  }
  if (!post) return <SitePage title={t('auto.magazine__slug__page.s_d45e5bd4')} />;

  const [origin, publisher] = await Promise.all([siteOrigin(), siteName()]);
  return (
    <SitePage kicker={t('site.nav.magazine')} title={post.title} lead={post.excerpt || undefined}>
      <JsonLd data={[
        articleLd(origin, { title: post.title, path: `/magazine/${slug}`, description: post.excerpt || post.body, image: post.cover_url, published: post.published_at, modified: post.updated_at, author: post.author, publisher }),
        breadcrumbLd(origin, [{ name: 'خانه', path: '/' }, { name: 'مجله', path: '/magazine' }, { name: post.title, path: `/magazine/${slug}` }]),
      ]} />
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
    </SitePage>
  );
}
