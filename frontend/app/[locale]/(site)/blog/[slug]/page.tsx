import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

type BlogPost = { title: string; body?: string | null; published_at?: string };

export default async function BlogPostPage({ params: { slug } }: { params: { locale: string; slug: string } }) {
  let post: BlogPost | null = null;
  try {
    const res = await apiServer<{ data: BlogPost }>(`/v1/public/blog/${slug}`);
    post = res.data;
  } catch { /* empty */ }

  if (!post) return <div className="container mx-auto px-4 py-12">مطلب یافت نشد.</div>;

  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{post.title}</h1>
      <div className="prose prose-neutral mt-8 max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: post.body ?? '' }} />
    </article>
  );
}
