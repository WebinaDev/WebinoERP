import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type BlogPostSummary = { id: number; slug: string; title: string; excerpt?: string | null };

export default async function BlogIndexPage() {
  const t = await getTranslations();
  let posts: BlogPostSummary[] = [];
  try {
    const res = await apiServer<{ data: BlogPostSummary[] }>('/v1/public/blog?per_page=12');
    posts = res.data ?? [];
  } catch {
    posts = [];
  }

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.nav.blog')} lead={t('site.page.blogLead')}>
      {posts.length ? (
        <ul className="card-grid cols-3">
          {posts.map((p) => (
            <li key={p.id}>
              <Link href={siteHref(undefined, `blog/${p.slug}`)} className="panel block">
                <h2 className="font-bold">{p.title}</h2>
                {p.excerpt ? <p className="muted mt-2 line-clamp-3 text-sm">{p.excerpt}</p> : null}
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyNote>{t('site.landing.blogEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
