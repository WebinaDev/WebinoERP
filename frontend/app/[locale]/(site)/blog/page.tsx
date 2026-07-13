import Link from 'next/link';
import { apiServer, siteHref } from '@/src/lib/api-server';

export const revalidate = 60;

type BlogPostSummary = { id: number; slug: string; title: string; excerpt?: string | null };

export default async function BlogIndexPage({ params: { locale } }: { params: { locale: string } }) {
  let posts: BlogPostSummary[] = [];
  try {
    const res = await apiServer<{ data: BlogPostSummary[] }>('/v1/public/blog?per_page=12');
    posts = res.data ?? [];
  } catch { /* empty */ }

  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">وبلاگ</h1>
      <ul className="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {posts.map((p) => (
          <li key={p.id} className="rounded-xl border p-5 hover:shadow-md">
            <Link href={siteHref(undefined, `blog/${p.slug}`)} className="block">
              <h2 className="font-semibold">{p.title}</h2>
              {p.excerpt ? <p className="text-muted-foreground mt-2 line-clamp-3 text-sm">{p.excerpt}</p> : null}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
