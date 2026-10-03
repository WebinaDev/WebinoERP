import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type BlogPost = { title: string; body?: string | null; excerpt?: string | null };

export default async function BlogPostPage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let post: BlogPost | null = null;
  try {
    const res = await apiServer<{ data: BlogPost }>(`/v1/public/blog/${slug}`);
    post = res.data;
  } catch {
    post = null;
  }
  if (!post) return <SitePage title={t('auto.blog__slug__page.s_7ccc3cc0')} />;

  return (
    <SitePage kicker={t('site.nav.blog')} title={post.title} lead={post.excerpt || undefined}>
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
      <Link href={siteHref(undefined, 'blog')} className="mt-8 inline-block text-sm font-bold text-[var(--saffron-deep)]">
        {t('site.nav.blog')}
      </Link>
    </SitePage>
  );
}
